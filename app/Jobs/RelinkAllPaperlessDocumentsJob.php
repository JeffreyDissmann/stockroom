<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PaperlessLink;
use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessLinker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Operator repair tool (#7): walks every distinct Paperless document id that
 * local items currently link to and re-applies the Stockroom annotation on
 * the Paperless side — the linked tag plus the `Stockroom URL` custom field
 * — by calling the same `annotateProcessed` path the intake job uses. Each
 * doc's display metadata (title/type/correspondent) is refreshed on the
 * local link rows in the same pass — this is THE backfill and staleness
 * story for the snapshot columns: pre-metadata rows (and adopt-command
 * rows, which are created bare) fill in here, and a rename in Paperless
 * heals on the next run.
 *
 * Use cases: a user deleted the linked tag in Paperless and wants it back;
 * the Stockroom APP_URL changed and existing docs still point at the old
 * host; an intake run silently dropped the annotation step because of a
 * transient Paperless outage; link rows showing stale or missing titles.
 *
 * Idempotent — annotateProcessed is a set-arithmetic tag swap plus a
 * single-key custom-field write, so running this twice produces the same
 * end state. Tries(1) because the whole job is itself a manual retry tool;
 * retrying it on the queue level would just amplify a Paperless outage.
 */
#[Tries(1)]
class RelinkAllPaperlessDocumentsJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public const STATUS_KEY = 'paperless.relink';

    /**
     * @param  bool  $metadataOnly  Refresh the local title/type/correspondent
     *                              snapshot only, skipping the Paperless-side
     *                              tag + backlink PATCH. This is the read-only
     *                              (on Paperless) variant the daily scheduler
     *                              runs; the full relink stays a manual action.
     */
    public function __construct(public readonly bool $metadataOnly = false) {}

    public function handle(PaperlessClient $client, PaperlessLinker $linker): void
    {
        $linkField = (string) config('paperless.link_custom_field');
        $triggerTag = (string) config('paperless.trigger_tag');
        $linkedTag = (string) config('paperless.linked_tag');
        $mode = $this->metadataOnly ? 'metadata' : 'relink';

        // Unique doc ids only — many items can point at the same doc, but we
        // only need to PATCH each doc once. The explicit `select()` before
        // `distinct()` is what makes this column-distinct rather than row-
        // distinct: SELECT DISTINCT paperless_document_id … runs in SQL so a
        // big paperless_links table doesn't pull every link row into memory.
        $documentIds = PaperlessLink::query()
            ->select('paperless_document_id')
            ->distinct()
            ->orderBy('paperless_document_id')
            ->pluck('paperless_document_id')
            ->all();

        $total = count($documentIds);

        // Log the intent up front so a crash mid-batch still leaves a
        // record that the job ran and how much it was attempting.
        Log::info('paperless.relink.starting', ['total' => $total, 'mode' => $mode]);

        $this->putStatus(['state' => 'running', 'mode' => $mode, 'done' => 0, 'failed' => 0, 'total' => $total]);

        $ok = 0;
        $failed = 0;
        foreach ($documentIds as $docId) {
            try {
                // Refresh the local metadata snapshot from the same fetch
                // window. The extra document GET (annotateProcessed does its
                // own internally) is the price of keeping its one-PATCH
                // signature untouched; type/correspondent name lookups are
                // memoized on the client, so they cost ~one call per batch.
                $metadata = $linker->metadataFromDocument($client->document((int) $docId));
                PaperlessLink::query()
                    ->where('paperless_document_id', $docId)
                    ->update($metadata);

                // Metadata-only runs stop here — no writes back to Paperless.
                if (! $this->metadataOnly) {
                    $client->annotateProcessed(
                        (int) $docId,
                        $triggerTag,
                        $linkedTag,
                        $linkField,
                        PaperlessLink::stockroomBacklinkFor((int) $docId),
                    );
                }
                $ok++;
            } catch (Throwable $e) {
                // Per-doc failure (404 doc gone, transient 5xx, Guzzle
                // ConnectException on a DNS/timeout blip) doesn't abort the
                // batch — the whole point of a repair tool is to make
                // progress on whatever can be repaired. We catch Throwable,
                // not just PaperlessException, because Guzzle network-layer
                // errors bubble up untranslated.
                $failed++;
                Log::warning('paperless.relink.doc_failed', [
                    'document_id' => $docId,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->putStatus(['state' => 'running', 'mode' => $mode, 'done' => $ok, 'failed' => $failed, 'total' => $total]);
        }

        Log::info('paperless.relink.summary', [
            'total' => $total,
            'ok' => $ok,
            'failed' => $failed,
            'mode' => $mode,
        ]);

        $this->putStatus(['state' => 'done', 'mode' => $mode, 'done' => $ok, 'failed' => $failed, 'total' => $total]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->putStatus([
            'state' => 'failed',
            'error' => $exception?->getMessage() ?? 'Re-link failed.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function putStatus(array $status): void
    {
        // 1-hour TTL — long enough to span a slow batch + admin reload, short
        // enough that a stale "running" left behind by a process kill clears
        // itself instead of sticking forever.
        Cache::put(self::STATUS_KEY, $status, now()->addHour());
    }
}

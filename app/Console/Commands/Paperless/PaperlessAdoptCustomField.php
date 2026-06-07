<?php

declare(strict_types=1);

namespace App\Console\Commands\Paperless;

use App\Jobs\RelinkAllPaperlessDocumentsJob;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\PaperlessLink;
use App\Services\Paperless\PaperlessClient;
use Illuminate\Console\Command;

/**
 * Migrates pre-existing manual Paperless references into the proper
 * `paperless_links` table (#7).
 *
 * Before the Paperless integration shipped, users sometimes stored a doc
 * id or a Paperless URL in a custom field (e.g. "Rechnung Paperless" with
 * a URL value `https://paperless.example/documents/447/`). This command
 * walks one such field, parses each value, and creates the matching
 * PaperlessLink rows so the new UI surfaces them like any intake-created
 * link.
 *
 * Parser accepts:
 *   - bare numeric id: `447`
 *   - any URL containing `/documents/{id}/`: `https://…/documents/447/`
 *
 * Idempotent — re-running is safe; PaperlessLink uses firstOrCreate on
 * (item_id, paperless_document_id) so already-adopted rows aren't duplicated.
 *
 * Optionally with `--relink`, runs the RelinkAllPaperlessDocumentsJob
 * synchronously afterwards so the adopted docs immediately get the
 * Stockroom tag and backlink URL on the Paperless side — and, since the
 * adopted rows are created without display metadata (title/type), their
 * snapshot columns filled in. Without the flag, the next repair run does it.
 */
class PaperlessAdoptCustomField extends Command
{
    protected $signature = 'paperless:adopt-custom-field
        {field? : Custom field name or key holding the legacy Paperless link / doc id. Omit to list available fields.}
        {--relink : Also run the re-link job synchronously so Paperless gets the Stockroom tag + backlink URL right away}';

    protected $description = 'Migrate manually-stored Paperless references on a custom field into proper paperless_links rows';

    public function handle(): int
    {
        $needle = $this->argument('field');

        // No field passed → list what's available so the user can copy one
        // back into the next invocation. Same shape we use when the named
        // field isn't found.
        if ($needle === null) {
            $this->info('No field specified. Pass one of these as the first argument:');
            $this->listAvailableFields();

            return self::SUCCESS;
        }

        $field = CustomField::query()
            ->where('name', $needle)
            ->orWhere('key', $needle)
            ->first();

        if ($field === null) {
            $this->error("Custom field '{$needle}' not found (looked up by name and key).");
            $this->newLine();
            $this->line('Available fields:');
            $this->listAvailableFields();

            return self::FAILURE;
        }

        $this->info("Adopting from custom field <fg=cyan>{$field->name}</> (id={$field->id}, type={$field->type->value})…");

        $created = 0;
        $skipped = 0;
        $unparseable = 0;

        CustomFieldValue::query()
            ->where('custom_field_id', $field->id)
            ->chunkById(200, function ($values) use (&$created, &$skipped, &$unparseable): void {
                foreach ($values as $value) {
                    $docId = $this->parseDocumentId((string) $value->value);
                    if ($docId === null) {
                        $unparseable++;
                        $this->components->twoColumnDetail(
                            "item #{$value->item_id}",
                            '<fg=yellow>could not parse</> <fg=gray>'.trim((string) $value->value).'</>',
                        );

                        continue;
                    }

                    $link = PaperlessLink::firstOrCreate([
                        'item_id' => $value->item_id,
                        'paperless_document_id' => $docId,
                    ]);

                    if ($link->wasRecentlyCreated) {
                        $created++;
                        $this->components->twoColumnDetail("item #{$value->item_id}", "<fg=green>linked → doc #{$docId}</>");
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->newLine();
        $this->components->twoColumnDetail('Created', "<fg=green>{$created}</>");
        $this->components->twoColumnDetail('Already linked (skipped)', (string) $skipped);
        $this->components->twoColumnDetail('Unparseable values', $unparseable > 0 ? "<fg=yellow>{$unparseable}</>" : '0');

        if ($this->option('relink')) {
            $this->newLine();
            $this->info('Running re-link job synchronously so Paperless picks up the new annotations…');

            if (PaperlessClient::fromConfig() === null) {
                $this->warn('Paperless is not configured (PAPERLESS_URL / PAPERLESS_TOKEN missing) — skipping re-link.');

                return self::SUCCESS;
            }

            // dispatchSync runs the job through the queue pipeline on the
            // sync driver — same code path as the worker, just in-process.
            // That means the container resolves the PaperlessClient
            // type-hint on handle(), the failed() hook fires on exception
            // (writing the failed status to cache), and Queue events fire
            // normally. Calling `(new Job)->handle($client)` directly
            // would skip all of that.
            RelinkAllPaperlessDocumentsJob::dispatchSync();
            $this->info('Re-link complete.');
        }

        return self::SUCCESS;
    }

    /**
     * Print every defined custom field with its key, type, and how many
     * items currently have a value on it — so the operator can pick the
     * right one without guessing names. Url / text fields with a non-zero
     * count are the obvious candidates for "holds a Paperless reference".
     */
    private function listAvailableFields(): void
    {
        $fields = CustomField::query()
            ->withCount('values')
            ->orderBy('name')
            ->get();

        if ($fields->isEmpty()) {
            $this->warn('  (no custom fields defined yet)');

            return;
        }

        foreach ($fields as $field) {
            $count = (int) $field->values_count;
            $countLabel = $count === 0 ? '0 items' : "{$count} items";
            $this->components->twoColumnDetail(
                "<fg=cyan>{$field->name}</> <fg=gray>(key: {$field->key}, type: {$field->type->value})</>",
                "<fg=gray>{$countLabel}</>",
            );
        }
    }

    /**
     * Extract a Paperless document id from a custom-field value. Accepts a
     * bare integer string or any URL that contains `/documents/{id}` —
     * matches both with and without the trailing slash, so a hand-typed
     * Paperless URL works either way.
     */
    private function parseDocumentId(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        if (preg_match('#/documents/(\d+)/?#', $raw, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}

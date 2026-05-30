<?php

declare(strict_types=1);

namespace App\Services\Brave;

use App\Models\Item;
use App\Services\ItemImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Auto-cover" helper: search Brave for the item's default query, take the
 * first hit, run it through the same downloader/processor pipeline as the
 * manual image-picker UI, and attach it as the item's primary image.
 *
 * Used by the Paperless intake job to give newly-extracted items a cover
 * without the user having to open the find-image dialog. Returns true when
 * an image was attached, false on any failure path (no Brave key, empty
 * search query, no results, download/decode error) — failures are logged
 * but never thrown: the auto-cover is opportunistic, not load-bearing.
 */
class AttachFirstImage
{
    public function __construct(
        private readonly RemoteImageDownloader $downloader,
        private readonly ItemImageProcessor $processor,
    ) {}

    public function __invoke(Item $item): bool
    {
        if (! BraveImageSearchClient::isConfigured()) {
            return false;
        }

        $query = $item->defaultImageSearchQuery();
        if ($query === '') {
            return false;
        }

        try {
            // count=1 — we only ever take the top result, no point paying
            // for the larger payload.
            $results = BraveImageSearchClient::default()->search($query, 1);
            $url = (string) ($results[0]['image_url'] ?? '');
            if ($url === '') {
                return false;
            }

            $image = $this->downloader->download($url);
            $tempPath = tempnam(sys_get_temp_dir(), 'brave-auto-');
            if ($tempPath === false) {
                // Disk full / TMPDIR mis-set / sandbox restriction — bail
                // before we try to write to "". The empty-string cast
                // followed by file_put_contents("") explodes with a
                // misleading error otherwise.
                throw new \RuntimeException('Could not create temp file for Brave image download.');
            }

            try {
                file_put_contents($tempPath, $image->contents);
                $this->processor->store($item, new UploadedFile($tempPath, $image->filename, $image->mime, null, true));
            } finally {
                @unlink($tempPath);
            }

            $item->logImagesAdded(1);

            return true;
        } catch (Throwable $e) {
            Log::warning('brave.auto_attach_failed', [
                'item_id' => $item->id,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

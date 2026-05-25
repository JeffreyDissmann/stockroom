<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\Tag;
use App\Services\ItemImageProcessor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Restores a backup archive produced by {@see BackupExporter}. Items, tags and
 * images keep their original ids (upsert), so re-importing the same archive
 * updates rather than duplicates, and parent links restore verbatim. Derived
 * image variants are regenerated from the bundled originals.
 */
class BackupImporter
{
    /**
     * Item columns that are restored as-is from the manifest.
     *
     * @var list<string>
     */
    private const ITEM_FIELDS = [
        'type', 'name', 'description', 'quantity', 'purchased_from', 'purchase_date',
        'purchase_price', 'manufacturer', 'model_number', 'serial_number',
        'lifetime_warranty', 'warranty_expires', 'warranty_details',
        'sold_to', 'sold_price', 'sold_date', 'sold_notes',
    ];

    public function __construct(private readonly ItemImageProcessor $processor) {}

    /**
     * @return array{tags: int, items: int, images: int}
     */
    public function import(string $zipPath): array
    {
        $dir = $this->extract($zipPath);

        try {
            $manifest = $this->readJson("{$dir}/manifest.json");
            $this->assertSupported($manifest);
            $data = $this->readJson("{$dir}/data.json");

            $result = DB::transaction(function () use ($data, $dir): array {
                $tags = $data['tags'] ?? [];
                $items = $data['items'] ?? [];

                foreach ($tags as $tag) {
                    // The id is set as a property (not mass-assigned) to preserve it
                    // under Model::shouldBeStrict(), which guards non-fillable keys.
                    $record = Tag::query()->find($tag['id']) ?? new Tag;
                    $record->id = $tag['id'];
                    $record->fill(['name' => $tag['name'], 'color' => $tag['color'] ?? null]);
                    $record->save();
                }

                // Pass 1: upsert every item without its parent link so any
                // ordering of the array is safe against the self-referencing FK.
                foreach ($items as $row) {
                    $item = Item::query()->find($row['id']) ?? new Item;
                    $item->id = $row['id'];
                    $item->fill(Arr::only($row, self::ITEM_FIELDS));
                    $item->parent_id = null;
                    $item->save();
                }

                // Pass 2: wire up parents, tags and images now that all rows exist.
                $imageCount = 0;
                foreach ($items as $row) {
                    $item = Item::query()->findOrFail($row['id']);
                    $item->update(['parent_id' => $row['parent_id'] ?? null]);
                    $item->tags()->sync($row['tags'] ?? []);
                    $imageCount += $this->restoreImages($item, $row['images'] ?? [], $dir);
                }

                $this->resetSequences();

                return [
                    'tags' => count($tags),
                    'items' => count($items),
                    'images' => $imageCount,
                ];
            });

            // Rebuild the search index to match the restored data.
            Item::removeAllFromSearch();
            Item::makeAllSearchable();

            return $result;
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /**
     * Replace an item's images with the set described in the manifest, rebuilding
     * the derived variants from each bundled original.
     *
     * @param  list<array<string, mixed>>  $images
     */
    private function restoreImages(Item $item, array $images, string $dir): int
    {
        foreach ($item->images()->get() as $existing) {
            $existing->delete();
        }

        $restored = 0;
        foreach ($images as $meta) {
            $source = "{$dir}/images/{$meta['id']}/original.{$meta['extension']}";
            if (! is_file($source)) {
                continue;
            }

            $dimensions = getimagesize($source) ?: [0, 0];

            $record = new ItemImage;
            $record->id = $meta['id'];
            $record->item_id = $item->id;
            $record->extension = $meta['extension'];
            $record->mime_type = $meta['mime_type'] ?? 'application/octet-stream';
            $record->width_original = $dimensions[0];
            $record->height_original = $dimensions[1];
            $record->size_bytes_original = (int) (filesize($source) ?: 0);
            $record->sort_order = $meta['sort_order'] ?? 0;
            $record->is_primary = (bool) ($meta['is_primary'] ?? false);
            $record->save();

            $this->processor->writeVariantsFromPath($record, $source);
            $restored++;
        }

        return $restored;
    }

    private function extract(string $zipPath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw ValidationException::withMessages(['file' => 'The file could not be read as a zip archive.']);
        }

        $dir = (string) tempnam(sys_get_temp_dir(), 'stockroom-restore-');
        File::delete($dir);
        File::makeDirectory($dir);
        $zip->extractTo($dir);
        $zip->close();

        return $dir;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw ValidationException::withMessages(['file' => 'This is not a Stockroom backup archive.']);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['file' => 'The backup archive is corrupted.']);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertSupported(array $manifest): void
    {
        if (($manifest['format'] ?? null) !== BackupExporter::FORMAT) {
            throw ValidationException::withMessages(['file' => 'This is not a Stockroom backup archive.']);
        }

        if ((int) ($manifest['version'] ?? 0) > BackupExporter::VERSION) {
            throw ValidationException::withMessages(['file' => 'This backup was made by a newer version of Stockroom.']);
        }
    }

    /**
     * Keep auto-increment sequences ahead of the ids we inserted explicitly, so
     * subsequently created records don't collide. Postgres-only (the app's DB).
     */
    private function resetSequences(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['items', 'tags', 'item_images'] as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence(?, 'id'), GREATEST((SELECT COALESCE(MAX(id), 1) FROM {$table}), 1))",
                [$table],
            );
        }
    }
}

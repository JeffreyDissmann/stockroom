<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\Tag;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Builds a lossless backup archive: a JSON manifest describing the whole
 * inventory (item tree, tags, image metadata) plus the original image files.
 * Derived image variants (large/thumb) are reproducible and therefore omitted.
 */
class BackupExporter
{
    public const FORMAT = 'stockroom-backup';

    public const VERSION = 1;

    /**
     * Write the backup zip to a temporary file and return its absolute path.
     * The caller owns the file (e.g. streams it then deletes it).
     */
    public function export(): string
    {
        $tags = Tag::query()->orderBy('id')->get();
        $items = Item::query()->with(['tags:id', 'images'])->orderBy('id')->get();

        $data = [
            'tags' => $tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->all(),
            'items' => $items->map(fn (Item $item): array => $this->presentItem($item))->all(),
        ];

        $manifest = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'app' => config('app.name'),
            'counts' => [
                'tags' => $tags->count(),
                'items' => $items->count(),
                'images' => $items->sum(fn (Item $item): int => $item->images->count()),
            ],
        ];

        $path = tempnam(sys_get_temp_dir(), 'stockroom-backup-');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', $this->encode($manifest));
        $zip->addFromString('data.json', $this->encode($data));

        $disk = Storage::disk('public');
        foreach ($items as $item) {
            foreach ($item->images as $image) {
                if ($disk->exists($image->originalPath())) {
                    $zip->addFromString(
                        "images/{$image->id}/original.{$image->extension}",
                        $disk->get($image->originalPath()),
                    );
                }
            }
        }
        $zip->close();

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(Item $item): array
    {
        return [
            'id' => $item->id,
            'parent_id' => $item->parent_id,
            'type' => $item->type->value,
            'name' => $item->name,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'purchased_from' => $item->purchased_from,
            'purchase_date' => $item->purchase_date?->toDateString(),
            'purchase_price' => $item->purchase_price,
            'manufacturer' => $item->manufacturer,
            'model_number' => $item->model_number,
            'serial_number' => $item->serial_number,
            'lifetime_warranty' => $item->lifetime_warranty,
            'warranty_expires' => $item->warranty_expires?->toDateString(),
            'warranty_details' => $item->warranty_details,
            'sold_to' => $item->sold_to,
            'sold_price' => $item->sold_price,
            'sold_date' => $item->sold_date?->toDateString(),
            'sold_notes' => $item->sold_notes,
            'tags' => $item->tags->pluck('id')->all(),
            'images' => $item->images->map(fn (ItemImage $image): array => [
                'id' => $image->id,
                'extension' => $image->extension,
                'mime_type' => $image->mime_type,
                'sort_order' => $image->sort_order,
                'is_primary' => $image->is_primary,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

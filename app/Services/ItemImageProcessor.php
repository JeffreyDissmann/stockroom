<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\ItemImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class ItemImageProcessor
{
    private const ORIGINAL_MAX = 2048;

    private const LARGE_MAX = 1280;

    private const THUMB_SIZE = 200;

    private const QUALITY = 85;

    public function __construct(private readonly ImageManager $manager) {}

    public static function default(): self
    {
        return new self(new ImageManager(new Driver));
    }

    public function store(Item $item, UploadedFile $file): ItemImage
    {
        $extension = $this->normaliseExtension($file);
        $sourceImage = $this->manager->decode($file->getRealPath());

        return DB::transaction(function () use ($item, $file, $extension, $sourceImage): ItemImage {
            $isFirst = ! $item->images()->exists();
            $maxOrder = (int) $item->images()->max('sort_order');

            /** @var ItemImage $record */
            $record = $item->images()->create([
                'extension' => $extension,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'width_original' => $sourceImage->width(),
                'height_original' => $sourceImage->height(),
                'size_bytes_original' => $file->getSize() ?: 0,
                'sort_order' => $isFirst ? 0 : $maxOrder + 1,
                'is_primary' => $isFirst,
            ]);

            $this->writeVariants($record, $sourceImage);

            return $record;
        });
    }

    /**
     * Regenerate the derived variants (original/large/thumb) for an already-persisted
     * image record from a source file on disk. Used when restoring a backup, which
     * bundles only the original — everything else is reproducible.
     */
    public function writeVariantsFromPath(ItemImage $record, string $sourcePath): void
    {
        $this->writeVariants($record, $this->manager->decode($sourcePath));
    }

    private function writeVariants(ItemImage $record, ImageInterface $sourceImage): void
    {
        $extension = $record->extension;
        $disk = Storage::disk('public');
        $disk->makeDirectory($record->directory());

        // Original — contain to ORIGINAL_MAX. EXIF is dropped because we re-encode.
        $disk->put(
            $record->originalPath(),
            (string) (clone $sourceImage)
                ->scaleDown(self::ORIGINAL_MAX, self::ORIGINAL_MAX)
                ->encodeUsingFileExtension($extension, quality: self::QUALITY),
        );

        // Large — contain to LARGE_MAX.
        $disk->put(
            $record->largePath(),
            (string) (clone $sourceImage)
                ->scaleDown(self::LARGE_MAX, self::LARGE_MAX)
                ->encodeUsingFileExtension($extension, quality: self::QUALITY),
        );

        // Thumb — cover-crop to THUMB_SIZE × THUMB_SIZE.
        $disk->put(
            $record->thumbPath(),
            (string) (clone $sourceImage)
                ->cover(self::THUMB_SIZE, self::THUMB_SIZE)
                ->encodeUsingFileExtension($extension, quality: self::QUALITY),
        );
    }

    private function normaliseExtension(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');

        return match ($ext) {
            'jpeg' => 'jpg',
            default => $ext,
        };
    }
}

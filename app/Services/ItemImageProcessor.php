<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\ItemImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

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
        $sourceImage = $this->decodeSource($file->getRealPath());

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
     * Attach an image to an item from a file already on disk (rather than an
     * upload), e.g. a photo the assistant stashed while a "create this item"
     * request was confirmed. Mirrors store() but sources from a path.
     */
    public function storeFromPath(Item $item, string $path, string $extension = 'jpg', string $mimeType = 'image/jpeg'): ItemImage
    {
        $sourceImage = $this->decodeSource($path);

        return DB::transaction(function () use ($item, $path, $extension, $mimeType, $sourceImage): ItemImage {
            $isFirst = ! $item->images()->exists();
            $maxOrder = (int) $item->images()->max('sort_order');

            /** @var ItemImage $record */
            $record = $item->images()->create([
                'extension' => $extension,
                'mime_type' => $mimeType,
                'width_original' => $sourceImage->width(),
                'height_original' => $sourceImage->height(),
                'size_bytes_original' => @filesize($path) ?: 0,
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
        $this->writeVariants($record, $this->decodeSource($sourcePath));
    }

    /**
     * Decode an upload, scale it down to fit within $maxEdge, and return JPEG
     * bytes — a lightweight, EXIF-stripped copy for sending to an AI vision
     * model (decoding reuses the GD→Imagick fallback, so HEIC works too).
     */
    public function downscaleToJpeg(UploadedFile $file, int $maxEdge = 1280, int $quality = 80): string
    {
        return (string) $this->decodeSource($file->getRealPath())
            ->scaleDown($maxEdge, $maxEdge)
            ->encodeUsingFileExtension('jpg', quality: $quality);
    }

    /**
     * Decode a source image to a GD-backed image. Formats GD rejects (e.g. TIFF)
     * fall back to Imagick, which converts them to JPEG first.
     */
    private function decodeSource(string $path): ImageInterface
    {
        try {
            return $this->manager->decode($path);
        } catch (Throwable $e) {
            if (! extension_loaded('imagick')) {
                throw $e;
            }

            $imagick = new Imagick($path);
            $imagick->setIteratorIndex(0);
            $imagick->setImageFormat('jpeg');
            $jpeg = $imagick->getImageBlob();
            $imagick->clear();

            return $this->manager->decode($jpeg);
        }
    }

    private function writeVariants(ItemImage $record, ImageInterface $sourceImage): void
    {
        $extension = $record->extension;
        $disk = Storage::disk('public');
        $disk->makeDirectory($record->directory());

        // Variants are emitted in monotonic-shrink order so the source can be
        // mutated in place — no clones. Each `clone $sourceImage` would have
        // duplicated the decoded GD pixel buffer (~200 MB for a 6000×4000
        // photo), so three clones briefly coexisting could blow past the
        // 512 MB memory_limit during HomeBox imports of phone photos. With
        // in-place mutation peak memory stays at one pixel buffer.

        // Original — contain to ORIGINAL_MAX. EXIF is dropped because we re-encode.
        $sourceImage->scaleDown(self::ORIGINAL_MAX, self::ORIGINAL_MAX);
        $disk->put(
            $record->originalPath(),
            (string) $sourceImage->encodeUsingFileExtension($extension, quality: self::QUALITY),
        );

        // Large — further contain to LARGE_MAX (always ≤ ORIGINAL_MAX, so the
        // source is already at most ORIGINAL_MAX from the previous step).
        $sourceImage->scaleDown(self::LARGE_MAX, self::LARGE_MAX);
        $disk->put(
            $record->largePath(),
            (string) $sourceImage->encodeUsingFileExtension($extension, quality: self::QUALITY),
        );

        // Thumb — cover-crop to THUMB_SIZE × THUMB_SIZE. Source is ≥ LARGE_MAX
        // along the longer edge here, so `cover` never enlarges.
        $sourceImage->cover(self::THUMB_SIZE, self::THUMB_SIZE);
        $disk->put(
            $record->thumbPath(),
            (string) $sourceImage->encodeUsingFileExtension($extension, quality: self::QUALITY),
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

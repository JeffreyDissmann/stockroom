<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The rules every uploaded photo is held to, in one place.
 *
 * They were written out four times — the item form, the gallery upload, the
 * photo analyser and the assistant's inline validation — which meant raising the
 * size limit or accepting a new format was four edits, and nothing failed if you
 * only made three.
 *
 * Not a Requests concern trait, because one of the four callers is a controller
 * validating inline.
 */
final class ImageUploadRules
{
    /** Matches the "up to 10 MB each" the upload hint promises. */
    public const MAX_KILOBYTES = 10240;

    /** Below this an image is a thumbnail or a tracking pixel, not a photo. */
    public const MIN_EDGE_PIXELS = 64;

    /**
     * Per-file rules. The caller supplies presence (`required` / `nullable`),
     * because that differs by form.
     *
     * @return list<string>
     */
    public static function perFile(): array
    {
        return [
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp,heic',
            'max:'.self::MAX_KILOBYTES,
            'dimensions:min_width='.self::MIN_EDGE_PIXELS.',min_height='.self::MIN_EDGE_PIXELS,
        ];
    }
}

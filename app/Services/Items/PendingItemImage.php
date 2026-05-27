<?php

declare(strict_types=1);

namespace App\Services\Items;

use Illuminate\Support\Facades\Storage;

/**
 * Holds an image a user uploaded to the assistant while a "create this item"
 * request is confirmed across turns. The downscaled JPEG is stashed on the
 * local disk keyed by conversation id, then attached to the item once the
 * CreateItem tool runs (and cleared afterwards).
 */
class PendingItemImage
{
    private const DIRECTORY = 'assistant-pending';

    public function put(string $conversationId, string $jpeg): void
    {
        Storage::disk('local')->put($this->path($conversationId), $jpeg);
    }

    public function has(string $conversationId): bool
    {
        return Storage::disk('local')->exists($this->path($conversationId));
    }

    /**
     * Absolute filesystem path to the stashed image, or null if none is stored.
     */
    public function absolutePath(string $conversationId): ?string
    {
        return $this->has($conversationId)
            ? Storage::disk('local')->path($this->path($conversationId))
            : null;
    }

    public function forget(string $conversationId): void
    {
        Storage::disk('local')->delete($this->path($conversationId));
    }

    private function path(string $conversationId): string
    {
        return self::DIRECTORY.'/'.sha1($conversationId).'.jpg';
    }
}

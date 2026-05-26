<?php

declare(strict_types=1);

namespace App\Services\Brave;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Brave Search image API. The API key is host-provided via
 * config('services.brave.key'); when it is blank the feature is considered
 * disabled (see {@see isConfigured()}).
 */
class BraveImageSearchClient
{
    private const ENDPOINT = 'https://api.search.brave.com/res/v1/images/search';

    public function __construct(private readonly string $key) {}

    public static function isConfigured(): bool
    {
        return filled(config('services.brave.key'));
    }

    public static function default(): self
    {
        return new self((string) config('services.brave.key'));
    }

    /**
     * Search for images, returning a normalized, UI-ready list.
     *
     * @return list<array{title: string, thumb_url: string, image_url: string, source_url: string}>
     */
    public function search(string $query, int $count = 20): array
    {
        $count = max(1, min($count, 50));

        try {
            $response = Http::withHeaders([
                'X-Subscription-Token' => $this->key,
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->retry(2, 250)
                ->get(self::ENDPOINT, ['q' => $query, 'count' => $count])
                ->throw();
        } catch (RequestException $e) {
            // Surface rate limits (429) and outages as one clean failure mode.
            throw new BraveException('Image search is temporarily unavailable.', previous: $e);
        }

        return collect($response->json('results', []))
            ->map(fn (mixed $result): array => [
                'title' => (string) data_get($result, 'title', ''),
                'thumb_url' => (string) data_get($result, 'thumbnail.src', ''),
                'image_url' => (string) data_get($result, 'properties.url', ''),
                'source_url' => (string) data_get($result, 'url', ''),
            ])
            ->filter(fn (array $result): bool => $result['image_url'] !== '')
            ->values()
            ->all();
    }
}

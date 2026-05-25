<?php

declare(strict_types=1);

namespace App\Services\Homebox;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Homebox REST API (v0.25+, the unified "entities" API).
 * Holds only a short-lived bearer token — credentials are exchanged for it once
 * and never stored.
 */
class HomeboxClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {}

    /**
     * Exchange credentials for a token and return a ready client.
     */
    public static function login(string $baseUrl, string $username, string $password): self
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        $response = Http::asJson()
            ->timeout(20)
            ->post("{$baseUrl}/api/v1/users/login", [
                'username' => $username,
                'password' => $password,
                'stayLoggedIn' => true,
            ]);

        if ($response->status() === 401 || $response->status() === 400) {
            throw new HomeboxException('Homebox rejected those credentials.');
        }

        if ($response->failed()) {
            throw new HomeboxException('Could not reach Homebox at that address.');
        }

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new HomeboxException('Homebox did not return a sign-in token.');
        }

        return new self($baseUrl, $token);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function token(): string
    {
        return $this->token;
    }

    /**
     * One page of entities (locations + items).
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function entities(int $page, int $pageSize = 100): array
    {
        $response = $this->request()
            ->get("{$this->baseUrl}/api/v1/entities", ['page' => $page, 'pageSize' => $pageSize])
            ->throw();

        return [
            'items' => $response->json('items', []),
            'total' => (int) $response->json('total', 0),
        ];
    }

    /**
     * Every entity summary, across all pages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allEntities(int $pageSize = 100): array
    {
        $all = [];
        $page = 1;

        do {
            $batch = $this->entities($page, $pageSize);
            $all = array_merge($all, $batch['items']);
            $page++;
        } while (count($all) < $batch['total'] && $batch['items'] !== []);

        return $all;
    }

    /**
     * The nested location tree (locations are not returned by {@see entities()}).
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        return $this->request()
            ->get("{$this->baseUrl}/api/v1/entities/tree")
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function entity(string $id): array
    {
        return $this->request()
            ->get("{$this->baseUrl}/api/v1/entities/{$id}")
            ->throw()
            ->json();
    }

    public function downloadAttachment(string $entityId, string $attachmentId): string
    {
        return $this->request()
            ->get("{$this->baseUrl}/api/v1/entities/{$entityId}/attachments/{$attachmentId}")
            ->throw()
            ->body();
    }

    private function request(): PendingRequest
    {
        // The login token is already "Bearer …"-prefixed.
        return Http::withHeaders(['Authorization' => $this->token])
            ->timeout(30)
            ->retry(2, 250);
    }
}

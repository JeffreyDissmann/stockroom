<?php

declare(strict_types=1);

namespace App\Services\Paperless;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Paperless-ngx REST API. Only knows the operations the
 * webhook job needs:
 *
 *   - document($id)              : fetch a doc's content + metadata
 *   - download($id)              : raw bytes (kept lazy; not used in v1)
 *   - addTag($id, $tag)          : tag swap after processing
 *   - removeTag($id, $tag)       : ditto
 *   - setCustomField($id, $name, $value) : write the link-back back-reference
 *
 * Deliberately no `search`. Paperless has per-user permissions Stockroom
 * can't reasonably mirror, so the integration only ever touches docs a user
 * explicitly pushed to us via the workflow.
 */
class PaperlessClient
{
    private const TIMEOUT = 20;

    /**
     * Per-instance memoization for the two `/api/{tags,custom_fields}/?name__iexact=…`
     * lookups. Tag and field ids never change across an intake or unlink
     * cycle; resolving them once per client instance halves the round-trips
     * for annotateProcessed (one custom field + two tag resolves → three
     * cached lookups → 3 GETs to 0 on the hot path after the first call).
     * The cache is intentionally instance-local: a fresh PaperlessClient is
     * built per webhook / queue job, so stale ids can't survive a Paperless
     * admin renaming something between runs.
     *
     * @var array<string, int>
     */
    private array $tagIdCache = [];

    /** @var array<string, int> */
    private array $customFieldIdCache = [];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {}

    /**
     * Build a client from the configured env. Returns null when the
     * integration is disabled (no PAPERLESS_URL), so callers can branch
     * without re-checking config.
     */
    public static function fromConfig(): ?self
    {
        $url = (string) (config('paperless.url') ?? '');
        $token = (string) (config('paperless.token') ?? '');

        if ($url === '' || $token === '') {
            return null;
        }

        return new self(rtrim($url, '/'), $token);
    }

    /**
     * GET /api/documents/{id}/ — full payload incl. content, correspondent,
     * tags, custom_fields. Returns the decoded JSON array verbatim.
     *
     * @return array<string, mixed>
     */
    public function document(int $id): array
    {
        $response = $this->request()->get("/api/documents/{$id}/");

        if ($response->status() === 404) {
            throw new PaperlessException("Paperless document {$id} not found.");
        }

        $this->ensureOk($response, "fetching document {$id}");

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $payload;
    }

    /**
     * GET /api/documents/{id}/download/ — raw file bytes.
     */
    public function download(int $id): string
    {
        $response = $this->request()->get("/api/documents/{$id}/download/");

        $this->ensureOk($response, "downloading document {$id}");

        return $response->body();
    }

    /**
     * Append a tag to a doc by name. The Paperless API takes tag *ids*, so
     * we resolve the name to an id first. Idempotent — the API silently
     * accepts adding a tag that's already attached.
     */
    public function addTag(int $documentId, string $tagName): void
    {
        $tagId = $this->tagIdByName($tagName);
        $existing = $this->document($documentId)['tags'] ?? [];

        $this->patchDocument($documentId, [
            'tags' => array_values(array_unique([...array_map('intval', $existing), $tagId])),
        ]);
    }

    /**
     * Remove a tag from a doc by name. No-op if the tag isn't attached.
     */
    public function removeTag(int $documentId, string $tagName): void
    {
        $tagId = $this->tagIdByName($tagName);
        $existing = array_map('intval', $this->document($documentId)['tags'] ?? []);

        $this->patchDocument($documentId, [
            'tags' => array_values(array_diff($existing, [$tagId])),
        ]);
    }

    /**
     * Write a string into a Paperless custom field by name. Paperless expects
     * the full `custom_fields` array on PATCH, so we read-merge-write. Throws
     * if the named field hasn't been created in Paperless yet — admins must
     * create it once (typically `Stockroom URL`).
     */
    public function setCustomField(int $documentId, string $fieldName, ?string $value): void
    {
        $fieldId = $this->customFieldIdByName($fieldName);
        $doc = $this->document($documentId);
        $existing = $doc['custom_fields'] ?? [];

        $merged = [];
        $written = false;
        foreach ($existing as $entry) {
            if ((int) ($entry['field'] ?? 0) === $fieldId) {
                $merged[] = ['field' => $fieldId, 'value' => $value];
                $written = true;
            } else {
                $merged[] = $entry;
            }
        }
        if (! $written) {
            $merged[] = ['field' => $fieldId, 'value' => $value];
        }

        $this->patchDocument($documentId, ['custom_fields' => $merged]);
    }

    /**
     * Atomic post-intake annotation: tag swap (drop trigger tag, add linked
     * tag) and custom-field write in a single PATCH on `/api/documents/{id}/`.
     *
     * Avoiding the per-field PATCHes matters because Paperless fires a
     * DOCUMENT_UPDATED event per PATCH, and the intake workflow is itself
     * triggered by DOCUMENT_UPDATED. Spreading the writes across three calls
     * meant the first PATCH (custom field) fired the workflow again while
     * the trigger tag was still attached — same job, second time, duplicate
     * items. One PATCH = one event, evaluated against post-state (trigger
     * tag already gone), no re-fire.
     */
    public function annotateProcessed(
        int $documentId,
        string $triggerTagName,
        string $linkedTagName,
        string $customFieldName,
        ?string $customFieldValue,
    ): void {
        $triggerId = $this->tagIdByName($triggerTagName);
        $linkedId = $this->tagIdByName($linkedTagName);
        $fieldId = $this->customFieldIdByName($customFieldName);

        $doc = $this->document($documentId);

        $tags = array_values(array_unique([
            ...array_diff(array_map('intval', $doc['tags'] ?? []), [$triggerId]),
            $linkedId,
        ]));

        $merged = [];
        $written = false;
        foreach ($doc['custom_fields'] ?? [] as $entry) {
            if ((int) ($entry['field'] ?? 0) === $fieldId) {
                $merged[] = ['field' => $fieldId, 'value' => $customFieldValue];
                $written = true;
            } else {
                $merged[] = $entry;
            }
        }
        if (! $written) {
            $merged[] = ['field' => $fieldId, 'value' => $customFieldValue];
        }

        $this->patchDocument($documentId, [
            'tags' => $tags,
            'custom_fields' => $merged,
        ]);
    }

    /**
     * Read the string value of a named custom field on a doc, or null when
     * the field exists but isn't populated on this doc. Throws if the named
     * field isn't defined in Paperless at all (configuration error).
     */
    public function getCustomField(int $documentId, string $fieldName): ?string
    {
        $fieldId = $this->customFieldIdByName($fieldName);
        $doc = $this->document($documentId);

        foreach ($doc['custom_fields'] ?? [] as $entry) {
            if ((int) ($entry['field'] ?? 0) === $fieldId) {
                $value = $entry['value'] ?? null;

                return $value === null ? null : (string) $value;
            }
        }

        return null;
    }

    /**
     * Find a webhook-action workflow by name, or create one that fires on a
     * tag-added trigger and POSTs to $webhookUrl with the doc id as a
     * form param and a shared-secret header. Idempotent on name — existing
     * workflows are left alone (re-run after deleting in Paperless if the
     * URL or secret needs to change).
     *
     * @return array{0: int, 1: bool} [id, wasCreated]
     */
    public function ensureWorkflow(string $name, int $triggerTagId, string $webhookUrl, string $secret): array
    {
        // Single fetch covers both the existence check and the order
        // computation — saves a roundtrip vs the previous shape.
        $response = $this->request()->get('/api/workflows/');
        $this->ensureOk($response, 'listing workflows');
        $workflows = collect($response->json('results') ?? []);

        $match = $workflows->first(fn ($w) => strcasecmp((string) ($w['name'] ?? ''), $name) === 0);
        if (is_array($match) && isset($match['id'])) {
            return [(int) $match['id'], false];
        }

        // Place after any existing workflows so we don't reorder the user's
        // existing automation. Paperless runs workflows in ascending `order`,
        // so highest+1 puts us last.
        $order = (int) ($workflows->max(fn ($w) => (int) ($w['order'] ?? 0)) ?? 0) + 1;

        // Paperless workflow shape: a DOCUMENT_UPDATED trigger (type 3) fires
        // whenever a doc's properties change, including a tag being added.
        // `filter_has_tags` narrows it to docs that gained the trigger tag.
        // The webhook action (type 4) POSTs form params; Paperless's
        // workflow placeholder set (see paperless-ngx/src/documents/templating/
        // workflows.py) doesn't expose the doc id directly — only `doc_url`,
        // which we pattern-match for the trailing integer on our side.
        // Templates use Django-style double-brace `{{ }}` syntax. Shared
        // secret rides as a static header for auth.
        $payload = [
            'name' => $name,
            'order' => $order,
            'enabled' => true,
            'triggers' => [[
                'type' => 3,
                'sources' => [],
                'filter_has_tags' => [$triggerTagId],
                'matching_algorithm' => 0,
                'match' => '',
                'is_insensitive' => true,
            ]],
            'actions' => [[
                'type' => 4,
                'webhook' => [
                    'url' => $webhookUrl,
                    'use_params' => true,
                    'as_json' => false,
                    'params' => ['doc_url' => '{{doc_url}}'],
                    'body' => null,
                    'headers' => ['X-Stockroom-Secret' => $secret],
                    'include_document' => false,
                ],
            ]],
        ];

        $response = $this->request()->post('/api/workflows/', $payload);

        $this->ensureOk($response, "creating workflow '{$name}'");

        return [(int) $response->json('id'), true];
    }

    /**
     * Find a tag by name, or create it. Returns the id either way.
     *
     * @return array{0: int, 1: bool} [id, wasCreated]
     */
    public function ensureTag(string $name): array
    {
        $existing = $this->findTagId($name);
        if ($existing !== null) {
            return [$existing, false];
        }

        $response = $this->request()->post('/api/tags/', ['name' => $name]);

        $this->ensureOk($response, "creating tag '{$name}'");

        $id = (int) $response->json('id');
        $this->tagIdCache[$name] = $id;

        return [$id, true];
    }

    /**
     * Find a custom field by name, or create one with the given data_type.
     * Paperless data types are strings: 'string', 'integer', 'float',
     * 'monetary', 'boolean', 'date', 'url', 'documentlink', 'select'.
     *
     * @return array{0: int, 1: bool} [id, wasCreated]
     */
    public function ensureCustomField(string $name, string $dataType = 'string'): array
    {
        $existing = $this->findCustomFieldId($name);
        if ($existing !== null) {
            return [$existing, false];
        }

        $response = $this->request()->post('/api/custom_fields/', [
            'name' => $name,
            'data_type' => $dataType,
        ]);

        $this->ensureOk($response, "creating custom field '{$name}'");

        $id = (int) $response->json('id');
        $this->customFieldIdCache[$name] = $id;

        return [$id, true];
    }

    private function findTagId(string $name): ?int
    {
        if (isset($this->tagIdCache[$name])) {
            return $this->tagIdCache[$name];
        }

        $response = $this->request()->get('/api/tags/', ['name__iexact' => $name]);

        $this->ensureOk($response, "looking up tag '{$name}'");

        $match = collect($response->json('results') ?? [])
            ->first(fn ($t) => strcasecmp((string) ($t['name'] ?? ''), $name) === 0);

        $id = is_array($match) && isset($match['id']) ? (int) $match['id'] : null;

        // Only memoize positive hits — negative results would keep a not-yet-
        // created tag invisible after `ensureTag` provisions it later in the
        // same instance (the install command's hot path).
        if ($id !== null) {
            $this->tagIdCache[$name] = $id;
        }

        return $id;
    }

    private function findCustomFieldId(string $name): ?int
    {
        if (isset($this->customFieldIdCache[$name])) {
            return $this->customFieldIdCache[$name];
        }

        $response = $this->request()->get('/api/custom_fields/');

        $this->ensureOk($response, 'listing custom fields');

        $match = collect($response->json('results') ?? [])
            ->first(fn ($f) => strcasecmp((string) ($f['name'] ?? ''), $name) === 0);

        $id = is_array($match) && isset($match['id']) ? (int) $match['id'] : null;

        if ($id !== null) {
            $this->customFieldIdCache[$name] = $id;
        }

        return $id;
    }

    private function tagIdByName(string $name): int
    {
        $id = $this->findTagId($name);

        if ($id === null) {
            throw new PaperlessException("Paperless tag '{$name}' not found. Run `artisan paperless:install` to create it.");
        }

        return $id;
    }

    private function customFieldIdByName(string $name): int
    {
        $id = $this->findCustomFieldId($name);

        if ($id === null) {
            throw new PaperlessException("Paperless custom field '{$name}' not found. Run `artisan paperless:install` to create it.");
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function patchDocument(int $id, array $payload): void
    {
        $response = $this->request()->patch("/api/documents/{$id}/", $payload);

        $this->ensureOk($response, "patching document {$id}");
    }

    /**
     * Shared HTTP client preset: token-auth, JSON, base URL, timeout. Each
     * call returns a fresh PendingRequest so concurrent uses don't share
     * state.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token, 'Token')
            ->asJson()
            ->acceptJson()
            ->timeout(self::TIMEOUT);
    }

    private function ensureOk(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new PaperlessException("Paperless rejected the API token while {$context}.");
        }

        throw new PaperlessException("Paperless API error while {$context} (HTTP {$response->status()}).");
    }
}

<?php

declare(strict_types=1);

use App\Jobs\ProcessPaperlessDocumentJob;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
    config()->set('paperless.webhook_secret', 'test-secret');

    Bus::fake();
});

it('returns 404 when the integration is disabled', function () {
    config()->set('paperless.url', '');

    $this->post('/webhooks/paperless/document', ['document_id' => 42])
        ->assertNotFound();
});

it('returns 503 when the webhook secret is not configured', function () {
    config()->set('paperless.webhook_secret', '');

    $this->postJson('/webhooks/paperless/document', ['document_id' => 42])
        ->assertStatus(503);

    Bus::assertNothingDispatched();
});

it('returns 401 when the signature header is missing', function () {
    $this->postJson('/webhooks/paperless/document', ['document_id' => 42])
        ->assertUnauthorized();

    Bus::assertNothingDispatched();
});

it('returns 401 when the signature header is wrong', function () {
    $this->withHeader('X-Stockroom-Secret', 'wrong')
        ->postJson('/webhooks/paperless/document', ['document_id' => 42])
        ->assertUnauthorized();

    Bus::assertNothingDispatched();
});

it('dispatches the job with the document id when the secret matches', function () {
    $response = $this->withHeader('X-Stockroom-Secret', 'test-secret')
        ->postJson('/webhooks/paperless/document', ['document_id' => 42]);

    $response->assertStatus(202)
        ->assertJson(['accepted' => true, 'document_id' => 42]);

    Bus::assertDispatched(ProcessPaperlessDocumentJob::class, fn ($job) => $job->documentId === 42);
});

it('rejects a missing or invalid document_id', function () {
    $this->withHeader('X-Stockroom-Secret', 'test-secret')
        ->postJson('/webhooks/paperless/document', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('document_id');

    $this->withHeader('X-Stockroom-Secret', 'test-secret')
        ->postJson('/webhooks/paperless/document', ['document_id' => 'not-a-number'])
        ->assertStatus(422);

    Bus::assertNothingDispatched();
});

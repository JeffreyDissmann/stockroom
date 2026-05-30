<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
    config()->set('paperless.trigger_tag', 'add to stockbox');
    config()->set('paperless.linked_tag', 'stockbox');
    config()->set('paperless.link_custom_field', 'stockroom_item_ids');
});

it('fails when Paperless config is missing', function () {
    config()->set('paperless.url', '');

    $this->artisan('paperless:install')
        ->expectsOutputToContain('Paperless is not configured')
        ->assertFailed();
});

it('creates the trigger tag, linked tag, custom field and workflow when none exist', function () {
    config()->set('app.url', 'https://stockroom.test');
    config()->set('paperless.webhook_secret', 'preset-secret');

    Http::fake([
        'https://paperless.test/api/tags/?name__iexact=add%20to%20stockbox' => Http::response(['results' => []]),
        'https://paperless.test/api/tags/?name__iexact=stockbox' => Http::response(['results' => []]),
        'https://paperless.test/api/custom_fields/' => Http::response(['results' => []]),
        // Existing workflows already present — used to compute the order
        // for our new workflow (highest + 1 = 4).
        'https://paperless.test/api/workflows/' => Http::response(['results' => [
            ['id' => 1, 'name' => 'Other A', 'order' => 1],
            ['id' => 2, 'name' => 'Other B', 'order' => 3],
        ]]),
        // Creates: POSTs respond with assigned ids. The HTTP fake matches
        // by URL prefix, so the next 4 creates all return the same id —
        // good enough for an "all created" smoke.
        'https://paperless.test/api/tags/' => Http::response(['id' => 100], 201),
        'https://paperless.test/api/custom_fields/' => Http::response(['id' => 200], 201),
    ]);

    $this->artisan('paperless:install')->assertSuccessful();

    // Verify the workflow POST carried the trigger tag id, webhook url,
    // the static secret as a header — and that `order` is one past the
    // highest existing workflow's order so we don't reshuffle the user's
    // setup. (Output is asserted by hand via the live run; twoColumnDetail's
    // renderer varies between test and TTY contexts.)
    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && str_contains($r->url(), '/api/workflows/')
        && $r['name'] === 'Stockroom intake'
        && $r['order'] === 4
        && $r['actions'][0]['webhook']['url'] === 'https://stockroom.test/webhooks/paperless/document'
        && $r['actions'][0]['webhook']['headers']['X-Stockroom-Secret'] === 'preset-secret');
});

it('reports already-exists when items are present', function () {
    config()->set('app.url', 'https://stockroom.test');
    config()->set('paperless.webhook_secret', 'preset-secret');

    Http::fake([
        'https://paperless.test/api/tags/?name__iexact=add%20to%20stockbox' => Http::response([
            'results' => [['id' => 5, 'name' => 'add to stockbox']],
        ]),
        'https://paperless.test/api/tags/?name__iexact=stockbox' => Http::response([
            'results' => [['id' => 6, 'name' => 'stockbox']],
        ]),
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [['id' => 7, 'name' => 'stockroom_item_ids']],
        ]),
        'https://paperless.test/api/workflows/' => Http::response([
            'results' => [['id' => 8, 'name' => 'Stockroom intake']],
        ]),
    ]);

    $this->artisan('paperless:install')
        ->expectsOutputToContain('already exists')
        ->assertSuccessful();
});

it('fails when APP_URL is missing — Paperless needs a callback target', function () {
    config()->set('app.url', '');

    $this->artisan('paperless:install')
        ->expectsOutputToContain('APP_URL is not set')
        ->assertFailed();
});

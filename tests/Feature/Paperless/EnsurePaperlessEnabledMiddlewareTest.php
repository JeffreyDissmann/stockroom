<?php

declare(strict_types=1);

use App\Http\Middleware\EnsurePaperlessEnabled;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Register an ad-hoc route gated by the middleware so we can probe its
    // behaviour without wiring up the real webhook controller yet.
    Route::middleware(EnsurePaperlessEnabled::class)->get('/_paperless-probe', fn () => 'ok');
});

it('returns 404 when PAPERLESS_URL is empty (integration disabled)', function () {
    config()->set('paperless.url', '');

    $this->get('/_paperless-probe')->assertNotFound();
});

it('lets the request through when PAPERLESS_URL is configured', function () {
    config()->set('paperless.url', 'https://paperless.test');

    $this->get('/_paperless-probe')->assertOk()->assertSee('ok');
});

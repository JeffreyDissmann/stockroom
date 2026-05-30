<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust ANY proxy header. Stockroom is designed to run behind a
        // reverse proxy that terminates TLS (Caddy / Traefik / nginx /
        // Cloudflare Tunnel) — FrankenPHP only ever listens on plain HTTP
        // inside the container. Without this Laravel sees the request as
        // HTTP, route() generates http:// URLs, redirect()->intended()
        // emits a Location pointing at http://…, and the browser blocks
        // the cross-scheme XHR as mixed content — which is exactly the
        // post-2026.05.04 NAS login bug: POST /login → 302 to
        // http://…/dashboard → Axios Network Error → user stuck on
        // /login with no error shown.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Paperless workflow webhook (#7) authenticates via a static
        // shared-secret header (VerifyPaperlessSignature middleware), not
        // a session — so CSRF would always reject it. Exempt only the
        // exact path so the rest of the app keeps its CSRF protection.
        // `preventRequestForgery` is Laravel 13's replacement for the
        // now-deprecated `validateCsrfTokens`; same signature.
        $middleware->preventRequestForgery(except: [
            'webhooks/paperless/document',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

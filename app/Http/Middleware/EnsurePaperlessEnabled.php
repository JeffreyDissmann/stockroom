<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the Paperless integration routes. When `PAPERLESS_URL` is empty
 * the integration is off end-to-end: the webhook 404's, the AI agent
 * isn't registered, and the "From document" link on item Show doesn't
 * render. Returns 404 (not 503) on purpose — the integration is meant to
 * be invisible when disabled, not "temporarily unavailable".
 */
class EnsurePaperlessEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $url = (string) (config('paperless.url') ?? '');

        abort_if($url === '', 404);

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Static shared-secret guard for the Paperless webhook endpoint.
 *
 * Paperless's workflow webhook action can only send STATIC headers and
 * params — there's no per-body HMAC. So we use the simpler scheme:
 * `paperless:install` writes a random secret into both the Paperless
 * workflow's headers (`X-Stockroom-Secret: <secret>`) and Stockroom's
 * .env (`PAPERLESS_WEBHOOK_SECRET=<secret>`); this middleware
 * timing-safe-compares them on every request.
 *
 * Returns 401 on mismatch, 503 when the secret isn't configured (so a
 * misconfigured deploy doesn't silently accept anyone). The route is
 * also wrapped in EnsurePaperlessEnabled, which 404s before this runs
 * when PAPERLESS_URL is empty — so disabled-integration installs never
 * even hit this middleware.
 */
class VerifyPaperlessSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) (config('paperless.webhook_secret') ?? '');

        // Refuse to run if the secret hasn't been seeded. Better to fail
        // every incoming webhook than to accept them with no auth.
        abort_if($expected === '', 503, 'Paperless webhook secret is not configured.');

        $received = (string) $request->header('X-Stockroom-Secret', '');

        abort_unless(
            hash_equals($expected, $received),
            401,
            'Invalid Paperless webhook signature.',
        );

        return $next($request);
    }
}

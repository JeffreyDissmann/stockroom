<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the AI-powered routes. When AI is disabled the feature is off
 * end-to-end (the UI hides it too, via the features.ai shared prop).
 */
class EnsureAiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('ai.enabled'), 503, 'AI features are not enabled.');

        return $next($request);
    }
}

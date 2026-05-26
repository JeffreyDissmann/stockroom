<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Brave\BraveImageSearchClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the image-search routes: without a configured Brave key the feature is
 * off (the UI hides it too, via the features.imageSearch shared prop).
 */
class EnsureImageSearchEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(BraveImageSearchClient::isConfigured(), 503, 'Image search is not configured.');

        return $next($request);
    }
}

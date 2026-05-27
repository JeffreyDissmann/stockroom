<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the authenticated user's chosen locale to the whole request so
     * server-rendered labels, validation messages, and the shared translation
     * map all come out in their language. Guests fall back to the app default.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if ($locale && array_key_exists($locale, config('app.supported_locales'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}

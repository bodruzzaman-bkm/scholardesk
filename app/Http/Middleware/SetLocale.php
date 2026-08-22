<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the UI language for the request.
 *
 * A signed-in user's saved preference wins; guests fall back to a session
 * value set by the language toggle, then to the app default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale?->value
            ?? $request->session()->get('locale')
            ?? config('app.locale');

        if (in_array($locale, Locale::values(), true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}

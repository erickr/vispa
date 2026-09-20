<?php

namespace App\Http\Middleware;

use App\Support\SupportedLocales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs inside the panel's auth middleware, so the user is already resolved:
 * whatever they picked on their profile page becomes the request locale and
 * Filament's own translations follow along.
 */
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && SupportedLocales::isSupported($user->locale)) {
            App::setLocale($user->locale);
        }

        return $next($request);
    }
}

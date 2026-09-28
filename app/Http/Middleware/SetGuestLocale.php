<?php

namespace App\Http\Middleware;

use App\Support\PublicLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel's signed-out pages (login, registration) speak the same language as the public
 * ones: the visitor's pick on the landing page's toggle, else their browser. A signed-in user
 * is left to SetUserLocale, which runs later with their saved preference.
 */
class SetGuestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            App::setLocale(PublicLocale::resolve($request));
        }

        return $next($request);
    }
}

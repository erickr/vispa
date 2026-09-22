<?php

namespace App\Http\Controllers;

use App\Support\SupportedLocales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * The public front page. It speaks whichever language the visitor picked with the toggle
 * (remembered in the session), else the best match for their browser's Accept-Language.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request): View
    {
        if (SupportedLocales::isSupported($request->query('lang'))) {
            $request->session()->put('landing_locale', $request->query('lang'));
        }

        $locale = $request->session()->get('landing_locale')
            ?? $request->getPreferredLanguage(SupportedLocales::codes());

        App::setLocale(SupportedLocales::sanitize($locale));

        return view('welcome', ['locales' => SupportedLocales::options()]);
    }
}

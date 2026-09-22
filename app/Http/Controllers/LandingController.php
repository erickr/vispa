<?php

namespace App\Http\Controllers;

use App\Support\PublicLocale;
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
        App::setLocale(PublicLocale::resolve($request));

        return view('welcome', [
            'languages' => PublicLocale::links('landing'),
        ]);
    }
}

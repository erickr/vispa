<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\SharedRecipeController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

// Shared by uuid: an unlisted recipe's link has to be unguessable, and the slug table is
// optional and per locale.
Route::get('/r/{recipe:uuid}', SharedRecipeController::class)->name('recipes.share');

// Jetstream's family pages send guests to `login` and link home to `dashboard`; both belong
// to the panel. A guest following an invitation link comes back to it after signing in.
Route::redirect('/login', '/app/login')->name('login');
Route::redirect('/dashboard', '/app')->name('dashboard');

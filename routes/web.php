<?php

use App\Http\Controllers\AcceptFamilyInvitation;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\SharedRecipeController;
use App\Http\Middleware\SetUserLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

// Shared by uuid: an unlisted recipe's link has to be unguessable, and the slug table is
// optional and per locale.
Route::get('/r/{recipe:uuid}', SharedRecipeController::class)->name('recipes.share');

// Jetstream sends guests to `login`; signing in happens in the panel. A guest following an
// invitation link comes back to it afterwards.
Route::redirect('/login', '/app/login')->name('login');

// Our accept route, which FamilyInvitation links to (see the controller for why).
Route::get('/families/invitations/{invitation}', AcceptFamilyInvitation::class)
    ->middleware(['auth', 'signed', SetUserLocale::class])
    ->name('families.invitations.accept');

// Jetstream's own pages are replaced by the panel's family page and profile; its URIs are
// taken over here so its views — deleted — are never reached.
Route::get('/team-invitations/{invitation}', fn () => abort(404));
Route::redirect('/dashboard', '/app')->name('dashboard');
Route::redirect('/teams/create', '/app/family');
Route::redirect('/teams/{team}', '/app/family');
Route::redirect('/user/profile', '/app/profile');

<?php

use App\Http\Controllers\AcceptHouseholdInvitation;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\SaveSharedRecipe;
use App\Http\Controllers\SharedRecipeController;
use App\Http\Middleware\SetUserLocale;
use App\Models\Recipe;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

// Shared by uuid: an unlisted recipe's link has to be unguessable, and the slug table is
// optional and per locale.
Route::get('/r/{recipe:uuid}', SharedRecipeController::class)->name('recipes.share');

// Keeps a shared recipe in the visitor's household. A guest is sent to sign in first and comes
// back to the recipe page (the GET), since a POST cannot be replayed after the login redirect.
Route::post('/r/{recipe:uuid}/save', SaveSharedRecipe::class)
    ->middleware(['auth', SetUserLocale::class])
    ->name('recipes.share.save');
Route::get('/r/{recipe:uuid}/save', fn (Recipe $recipe) => redirect()->route('recipes.share', $recipe->uuid))
    ->middleware('auth')
    ->name('recipes.share.sign-in');

// Jetstream sends guests to `login`; signing in happens in the panel. A guest following an
// invitation link comes back to it afterwards.
Route::redirect('/login', '/app/login')->name('login');

// Our accept route, which HouseholdInvitationMail links to (see the controller for why).
Route::get('/households/invitations/{invitation}', AcceptHouseholdInvitation::class)
    ->middleware(['auth', 'signed', SetUserLocale::class])
    ->name('households.invitations.accept');

// Jetstream's own pages are replaced by the panel's household page and profile; its URIs are
// taken over here so its views — deleted — are never reached.
Route::get('/team-invitations/{invitation}', fn () => abort(404));
Route::redirect('/dashboard', '/app')->name('dashboard');
Route::redirect('/teams/create', '/app/household');
Route::redirect('/teams/{team}', '/app/household');
Route::redirect('/user/profile', '/app/profile');

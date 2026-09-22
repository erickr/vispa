<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\SharedRecipeController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

// Shared by uuid: an unlisted recipe's link has to be unguessable, and the slug table is
// optional and per locale.
Route::get('/r/{recipe:uuid}', SharedRecipeController::class)->name('recipes.share');

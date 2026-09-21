<?php

namespace App\Providers;

use Anthropic\Client;
use App\Recipes\Import\ClaudeRecipeExtractor;
use App\Recipes\Import\RecipeExtractor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RecipeExtractor::class, fn (): RecipeExtractor => new ClaudeRecipeExtractor(
            new Client(apiKey: (string) config('services.anthropic.key')),
            (string) config('services.anthropic.model'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

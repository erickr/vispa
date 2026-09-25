<?php

namespace App\Providers;

use Anthropic\Client;
use App\Actions\Households\CreatePersonalHousehold;
use App\Models\User;
use App\Recipes\Import\ClaudeRecipeExtractor;
use App\Recipes\Import\RecipeExtractor;
use Filament\Auth\Events\Registered as PanelRegistered;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
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
        // Every account starts with a household of its own to invite people into. The panel fires
        // its own event rather than Laravel's, so listen for both.
        Event::listen([Registered::class, PanelRegistered::class], function (Registered|PanelRegistered $event): void {
            $user = $event instanceof PanelRegistered ? $event->getUser() : $event->user;

            if ($user instanceof User) {
                app(CreatePersonalHousehold::class)->handle($user);
            }
        });
    }
}

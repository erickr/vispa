<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Household;
use App\Models\Recipe;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * One card per household the user is in, counting the recipes it holds. The current
 * household comes first; each card opens the recipe list narrowed to that household.
 */
class HouseholdRecipesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $households = $user->allTeams()
            ->sortBy(fn (Household $household): string => ($household->is($user->currentTeam) ? '0' : '1').mb_strtolower($household->name));

        $recipeCounts = Recipe::query()
            ->whereIn('household_id', $households->modelKeys())
            ->selectRaw('household_id, count(*) as aggregate')
            ->groupBy('household_id')
            ->pluck('aggregate', 'household_id');

        // The recipe list only offers the household filter to someone in several, and with
        // one household it already shows exactly that household's recipes.
        $filterByHousehold = $households->count() > 1;

        return $households
            ->map(fn (Household $household): Stat => Stat::make($household->name, (int) ($recipeCounts[$household->getKey()] ?? 0))
                ->description(trans_choice('household.widget.members', $household->users()->count() + 1))
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->icon(Heroicon::OutlinedBookOpen)
                ->url(RecipeResource::getUrl('index', $filterByHousehold
                    ? ['filters' => ['household_id' => ['value' => $household->getKey()]]]
                    : [])))
            ->values()
            ->all();
    }

    protected function getHeading(): ?string
    {
        return __('household.widget.heading');
    }
}

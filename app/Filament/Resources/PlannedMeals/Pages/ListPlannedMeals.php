<?php

namespace App\Filament\Resources\PlannedMeals\Pages;

use App\Actions\Meals\PlanMeal;
use App\Filament\Resources\PlannedMeals\PlannedMealResource;
use App\Filament\Resources\PlannedMeals\Schemas\PlanMealForm;
use App\Models\Recipe;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ListPlannedMeals extends ListRecords
{
    protected static string $resource = PlannedMealResource::class;

    public function getSubheading(): ?string
    {
        return __('planned_meal.page.subheading', ['household' => $this->user()->currentTeam?->name]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->planAction(),
        ];
    }

    public function planAction(): Action
    {
        return Action::make('plan')
            ->label(__('planned_meal.actions.plan'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(__('planned_meal.actions.plan_heading'))
            ->modalDescription(__('planned_meal.actions.plan_description'))
            ->modalSubmitActionLabel(__('planned_meal.actions.plan_submit'))
            ->schema(PlanMealForm::components())
            ->action(function (array $data): void {
                $day = filled($data['planned_for'] ?? null) ? Carbon::parse($data['planned_for']) : null;
                $plan = app(PlanMeal::class);

                $meal = ($data['source'] ?? null) === PlanMealForm::NEW_DISH
                    ? $plan->newDish($this->user(), $data['name'], $day)
                    : $plan->recipe($this->user(), Recipe::query()->findOrFail($data['recipe_id']), $day);

                Notification::make()
                    ->title(__('planned_meal.notifications.planned', [
                        'dish' => $meal->recipe->revisionFor($this->user())?->title ?? __('recipe.table.untitled'),
                    ]))
                    ->success()
                    ->send();
            });
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}

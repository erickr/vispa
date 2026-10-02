<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Actions\Meals\PlanMeal;
use App\Actions\Meals\RemovePlannedMeal;
use App\Filament\Resources\PlannedMeals\Pages\ListPlannedMeals;
use App\Filament\Resources\PlannedMeals\PlannedMealResource;
use App\Filament\Resources\PlannedMeals\Schemas\PlanMealForm;
use App\Filament\Resources\RecipeRevisions\Pages\ViewRecipeRevision;
use App\Models\Household;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\RecipeRating;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlannedMealsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    private function userWithHousehold(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(CreatePersonalHousehold::class)->handle($user);

        return $user->fresh();
    }

    private function join(User $user, Household $household): User
    {
        $household->users()->attach($user, ['role' => 'editor']);
        $user->switchTeam($household);

        return $user->fresh();
    }

    private function recipeBy(User $user, string $title, string $visibility = 'private', string $status = 'draft'): Recipe
    {
        $recipe = Recipe::create(['owner_user_id' => $user->getKey(), 'default_locale' => 'en', 'visibility' => $visibility]);
        $recipe->revisions()->create([
            'locale' => 'en', 'version_number' => 1, 'status' => $status,
            'title' => $title, 'created_by_user_id' => $user->getKey(),
        ]);

        return $recipe;
    }

    public function test_the_planned_meals_page_is_in_the_menu(): void
    {
        $this->actingAs($this->userWithHousehold('Eric Krona'));

        $this->get(PlannedMealResource::getUrl('index'))
            ->assertOk()
            ->assertSee(__('planned_meal.menu'))
            ->assertSee(__('planned_meal.table.empty_heading'));
    }

    public function test_a_recipe_of_ours_can_be_planned(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $pancakes = $this->recipeBy($eric, 'Pancakes');
        $this->actingAs($eric);

        Livewire::test(ListPlannedMeals::class)
            ->callAction('plan', [
                'source' => PlanMealForm::EXISTING,
                'recipe_id' => $pancakes->getKey(),
                'planned_for' => today()->addDay()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $meal = PlannedMeal::sole();
        $this->assertTrue($meal->recipe->is($pancakes));
        $this->assertSame($eric->current_household_id, $meal->household_id);
        $this->assertSame(today()->addDay()->toDateString(), $meal->planned_for->toDateString());

        Livewire::test(ListPlannedMeals::class)->assertCanSeeTableRecords([$meal])->assertSee('Pancakes');
    }

    public function test_a_new_dish_becomes_a_recipe_with_only_its_name(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $this->actingAs($eric);

        Livewire::test(ListPlannedMeals::class)
            ->callAction('plan', ['source' => PlanMealForm::NEW_DISH, 'name' => '  Meatballs '])
            ->assertHasNoActionErrors();

        $meal = PlannedMeal::sole();
        $recipe = $meal->recipe;
        $revision = $recipe->revisions()->sole();

        $this->assertNull($meal->planned_for);
        $this->assertSame($eric->getKey(), $recipe->owner_user_id);
        $this->assertSame($eric->current_household_id, $recipe->household_id);
        $this->assertSame('private', $recipe->visibility);
        $this->assertSame('Meatballs', $revision->title);
        $this->assertSame('draft', $revision->status);
        $this->assertTrue($revision->isLinkOnly());

        // A regular recipe: it is in the recipes list like any other.
        $this->assertTrue(Recipe::query()->accessibleTo($eric)->whereKey($recipe->getKey())->exists());
    }

    public function test_the_form_asks_for_what_the_chosen_source_needs(): void
    {
        $this->actingAs($this->userWithHousehold('Eric Krona'));

        Livewire::test(ListPlannedMeals::class)
            ->callAction('plan', ['source' => PlanMealForm::NEW_DISH, 'name' => ''])
            ->assertHasActionErrors(['name' => 'required']);

        Livewire::test(ListPlannedMeals::class)
            ->callAction('plan', ['source' => PlanMealForm::EXISTING, 'recipe_id' => null])
            ->assertHasActionErrors(['recipe_id' => 'required']);

        $this->assertSame(0, PlannedMeal::count());
        $this->assertSame(0, Recipe::count());
    }

    public function test_the_picker_offers_our_recipes_and_saved_ones_but_not_strangers(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $stranger = $this->userWithHousehold('Sam Stranger');

        $ours = $this->recipeBy($eric, 'Pancakes');
        $saved = $this->recipeBy($stranger, 'Their soup', 'public', 'published');
        $eric->currentTeam->savedRecipes()->attach($saved->getKey());
        $hidden = $this->recipeBy($stranger, 'Secret stew');

        $this->actingAs($eric);
        $options = PlanMealForm::recipeOptions();

        $this->assertSame('Pancakes', $options[$ours->getKey()] ?? null);
        $this->assertSame('Their soup', $options[$saved->getKey()] ?? null);
        $this->assertArrayNotHasKey($hidden->getKey(), $options);
    }

    public function test_a_recipe_the_user_cannot_open_cannot_be_planned(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $secret = $this->recipeBy($this->userWithHousehold('Sam Stranger'), 'Secret stew');

        $this->expectException(AuthorizationException::class);

        app(PlanMeal::class)->recipe($eric, $secret);
    }

    public function test_the_household_shares_one_plan(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $anna = $this->join($this->userWithHousehold('Anna Svensson'), $eric->currentTeam);
        $stranger = $this->userWithHousehold('Sam Stranger');

        $meal = app(PlanMeal::class)->newDish($eric, 'Tacos');

        $this->actingAs($anna);
        Livewire::test(ListPlannedMeals::class)
            ->assertCanSeeTableRecords([$meal])
            ->callTableAction('remove', $meal);
        $this->assertModelMissing($meal);
        // Taking it off the plan leaves the recipe.
        $this->assertSame(1, Recipe::count());

        $theirs = app(PlanMeal::class)->newDish($eric, 'Lasagne');
        $this->actingAs($stranger);
        Livewire::test(ListPlannedMeals::class)->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_past_days_are_hidden_by_default_and_undated_meals_come_last(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $plan = app(PlanMeal::class);

        $someday = $plan->newDish($eric, 'Someday soup');
        $tomorrow = $plan->newDish($eric, 'Tomorrow tacos', today()->addDay());
        $today = $plan->newDish($eric, 'Today toast', today());
        $yesterday = $plan->newDish($eric, 'Yesterday yams', today()->subDay());

        $this->actingAs($eric);
        Livewire::test(ListPlannedMeals::class)
            ->assertCanSeeTableRecords([$today, $tomorrow, $someday], inOrder: true)
            ->assertCanNotSeeTableRecords([$yesterday]);
    }

    public function test_the_day_can_be_changed(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $meal = app(PlanMeal::class)->newDish($eric, 'Tacos');

        $this->actingAs($eric);
        Livewire::test(ListPlannedMeals::class)
            ->callTableAction('changeDay', $meal, ['planned_for' => today()->addDays(3)->toDateString()])
            ->assertHasNoTableActionErrors();

        $this->assertSame(today()->addDays(3)->toDateString(), $meal->fresh()->planned_for->toDateString());
    }

    public function test_a_meal_can_be_removed_with_a_rating(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $meal = app(PlanMeal::class)->newDish($eric, 'Tacos');
        $revision = $meal->recipe->revisions()->sole();

        $this->actingAs($eric);
        Livewire::test(ListPlannedMeals::class)
            ->callTableAction('remove', $meal, ['rating' => 4])
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($meal);
        $rating = RecipeRating::sole();
        $this->assertSame(4, $rating->rating);
        $this->assertTrue($rating->recipe->is($meal->recipe));
        $this->assertTrue($rating->revision->is($revision));
        $this->assertSame($eric->current_household_id, $rating->household_id);
        $this->assertSame($eric->getKey(), $rating->rated_by_user_id);
    }

    public function test_the_rating_is_optional_since_the_dish_may_not_have_been_cooked(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $meal = app(PlanMeal::class)->newDish($eric, 'Tacos');

        $this->actingAs($eric);
        Livewire::test(ListPlannedMeals::class)
            ->callTableAction('remove', $meal)
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($meal);
        $this->assertSame(0, RecipeRating::count());
    }

    public function test_a_rating_must_be_between_one_and_five(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $meal = app(PlanMeal::class)->newDish($eric, 'Tacos');

        $this->actingAs($eric);
        Livewire::test(ListPlannedMeals::class)
            ->callTableAction('remove', $meal, ['rating' => 6])
            ->assertHasTableActionErrors(['rating']);

        $this->assertModelExists($meal);
        $this->assertSame(0, RecipeRating::count());
    }

    public function test_a_stranger_cannot_remove_or_rate_another_households_meal(): void
    {
        $meal = app(PlanMeal::class)->newDish($this->userWithHousehold('Eric Krona'), 'Tacos');

        try {
            app(RemovePlannedMeal::class)->handle($this->userWithHousehold('Sam Stranger'), $meal, 5);
            $this->fail('A stranger removed the meal.');
        } catch (AuthorizationException) {
        }

        $this->assertModelExists($meal);
        $this->assertSame(0, RecipeRating::count());
    }

    public function test_the_recipe_shows_the_households_last_rating_and_when_it_was_given(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $recipe = $this->recipeBy($eric, 'Tacos');
        $revision = $recipe->revisions()->sole();

        $this->travelTo(now()->setDate(2026, 9, 1));
        app(RemovePlannedMeal::class)->handle($eric, app(PlanMeal::class)->recipe($eric, $recipe), 2);
        $this->travelTo(now()->setDate(2026, 9, 20));
        app(RemovePlannedMeal::class)->handle($eric, app(PlanMeal::class)->recipe($eric, $recipe), 5);

        $this->actingAs($eric);
        Livewire::test(ViewRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertSee('Last rating')
            ->assertSee('5 of 5 · A favourite')
            ->assertSee('20 September 2026')
            ->assertDontSee('2 of 5');
    }

    public function test_no_rating_is_shown_before_the_dish_has_been_rated(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $revision = $this->recipeBy($eric, 'Tacos')->revisions()->sole();

        $this->actingAs($eric);
        Livewire::test(ViewRecipeRevision::class, ['record' => $revision->getKey()])
            ->assertDontSee('Last rating');
    }

    public function test_the_rating_is_hidden_while_the_user_is_in_another_household(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $home = $eric->currentTeam;
        $recipe = $this->recipeBy($eric, 'Tacos');
        app(RemovePlannedMeal::class)->handle($eric, app(PlanMeal::class)->recipe($eric, $recipe), 4);

        $this->assertNotNull($recipe->latestRatingFor($eric));

        // Still Eric's recipe, but he is looking from another household now.
        $other = $this->userWithHousehold('Anna Krona');
        $eric = $this->join($eric->fresh(), $other->currentTeam);
        $this->assertTrue($eric->current_household_id === $other->current_household_id);

        $this->assertNull($recipe->latestRatingFor($eric));

        $this->actingAs($eric);
        Livewire::test(ViewRecipeRevision::class, ['record' => $recipe->revisions()->sole()->getKey()])
            ->assertDontSee('Last rating');

        $eric->fresh()->switchTeam($home);
        $this->assertNotNull($recipe->latestRatingFor($eric->fresh()));
    }
}

<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Widgets\HouseholdRecipesOverview;
use App\Models\Household;
use App\Models\Recipe;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HouseholdRecipesOverviewTest extends TestCase
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

    private function recipesIn(Household $household, User $owner, int $count): void
    {
        foreach (range(1, $count) as $_) {
            Recipe::create([
                'owner_user_id' => $owner->id,
                'household_id' => $household->id,
                'default_locale' => 'en',
                'visibility' => 'private',
            ]);
        }
    }

    /**
     * @return array<int, Stat>
     */
    private function statsFor(User $user): array
    {
        $this->actingAs($user);

        return (fn () => $this->getStats())->call(new HouseholdRecipesOverview);
    }

    public function test_one_card_per_household_with_its_recipe_count(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $anna = $this->userWithHousehold('Anna Svensson');
        $jane = $this->userWithHousehold('Jane Doe');
        $eric->currentTeam->users()->attach($anna);

        $this->recipesIn($eric->currentTeam, $eric, 3);
        $this->recipesIn($eric->currentTeam, $anna, 1);
        $this->recipesIn($anna->currentTeam, $anna, 2);
        $this->recipesIn($jane->currentTeam, $jane, 5);

        $stats = $this->statsFor($anna->fresh());

        // Anna's current household first, then the one she joined; Jane's is none of hers.
        $this->assertSame(['The Svensson household', 'The Krona household'], array_map(fn (Stat $stat) => $stat->getLabel(), $stats));
        $this->assertSame([2, 4], array_map(fn (Stat $stat) => $stat->getValue(), $stats));
        $this->assertSame(['1 member', '2 members'], array_map(fn (Stat $stat) => $stat->getDescription(), $stats));

        // Each card opens the recipe list narrowed to its household.
        $this->assertStringEndsWith(
            '/app/recipes?'.http_build_query(['filters' => ['household_id' => ['value' => $eric->current_household_id]]]),
            $stats[1]->getUrl(),
        );
    }

    public function test_an_empty_household_shows_zero_and_links_to_the_plain_list(): void
    {
        $stats = $this->statsFor($this->userWithHousehold('Eric Krona'));

        $this->assertCount(1, $stats);
        $this->assertSame(0, $stats[0]->getValue());
        $this->assertStringEndsWith('/app/recipes', $stats[0]->getUrl());
    }

    public function test_it_is_on_the_dashboard(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $this->recipesIn($eric->currentTeam, $eric, 2);

        $this->actingAs($eric);
        $this->get(Filament::getUrl())->assertOk()->assertSeeLivewire(HouseholdRecipesOverview::class);

        Livewire::test(HouseholdRecipesOverview::class)
            ->assertSee('The Krona household')
            ->assertSee('Recipes per household');
    }

    public function test_the_household_link_filters_the_recipe_list(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $anna = $this->userWithHousehold('Anna Svensson');
        $eric->currentTeam->users()->attach($anna);
        $this->recipesIn($eric->currentTeam, $eric, 1);
        $this->recipesIn($anna->currentTeam, $anna, 1);

        $kronas = Recipe::where('household_id', $eric->current_household_id)->get();
        $svenssons = Recipe::where('household_id', $anna->fresh()->current_household_id)->get();

        $this->actingAs($anna->fresh());
        Livewire::withQueryParams(['filters' => ['household_id' => ['value' => $eric->current_household_id]]])
            ->test(ListRecipes::class)
            ->assertCanSeeTableRecords($kronas)
            ->assertCanNotSeeTableRecords($svenssons);
    }
}

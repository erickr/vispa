<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Mail\HouseholdInvitationMail;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\Household;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class HouseholdRecipesTest extends TestCase
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

        return $user->fresh();
    }

    private function recipeBy(User $user, string $title): Recipe
    {
        $recipe = Recipe::create(['owner_user_id' => $user->getKey(), 'default_locale' => 'en', 'visibility' => 'private']);
        $recipe->revisions()->create([
            'locale' => 'en', 'version_number' => 1, 'status' => 'draft',
            'title' => $title, 'created_by_user_id' => $user->getKey(),
        ]);

        return $recipe;
    }

    public function test_a_new_recipe_goes_into_the_owners_current_household(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');

        $this->assertSame($eric->current_household_id, $this->recipeBy($eric, 'Pancakes')->household_id);
    }

    public function test_household_members_see_and_edit_each_others_recipes(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $anna = $this->join($this->userWithHousehold('Anna Svensson'), $eric->currentTeam);
        $stranger = $this->userWithHousehold('Sam Stranger');

        $pancakes = $this->recipeBy($eric, 'Pancakes');
        $revision = $pancakes->revisions()->first();

        $this->actingAs($anna);
        Livewire::test(ListRecipes::class)->assertCanSeeTableRecords([$pancakes]);
        Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->fillForm(['title' => 'Better pancakes'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Better pancakes', $revision->fresh()->title);

        $this->actingAs($stranger);
        Livewire::test(ListRecipes::class)->assertCanNotSeeTableRecords([$pancakes]);
        $this->assertFalse(Recipe::accessibleTo($stranger)->whereKey($pancakes)->exists());
        $this->assertFalse(RecipeRevision::whereHas('recipe', fn ($q) => $q->accessibleTo($stranger))->whereKey($revision)->exists());
    }

    public function test_a_recipe_can_be_created_in_another_household(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $anna = $this->join($this->userWithHousehold('Anna Svensson'), $eric->currentTeam);

        $this->actingAs($anna);
        Livewire::test(CreateRecipe::class)
            ->assertFormFieldIsVisible('household_id')
            ->fillForm(['household_id' => $eric->current_household_id, 'default_locale' => 'en', 'visibility' => 'private', 'title' => 'Waffles'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($eric->current_household_id, Recipe::latest('id')->first()->household_id);
    }

    public function test_a_recipe_cannot_be_put_into_someone_elses_household(): void
    {
        $anna = $this->userWithHousehold('Anna Svensson');
        $this->join($anna, $this->userWithHousehold('Eric Krona')->currentTeam);
        $stranger = $this->userWithHousehold('Sam Stranger');

        $this->actingAs($anna->fresh());
        Livewire::test(CreateRecipe::class)
            ->fillForm(['household_id' => $stranger->current_household_id, 'default_locale' => 'en', 'visibility' => 'private'])
            ->call('create')
            ->assertHasFormErrors(['household_id']);
    }

    public function test_the_household_field_is_hidden_with_only_one_household(): void
    {
        $this->actingAs($this->userWithHousehold('Eric Krona'));

        Livewire::test(CreateRecipe::class)->assertFormFieldIsHidden('household_id');
    }

    public function test_existing_recipes_move_into_their_owners_household(): void
    {
        $eric = User::factory()->create(['name' => 'Eric Krona']);
        $recipe = $this->recipeBy($eric, 'Pancakes');
        $this->assertNull($recipe->household_id);

        app(CreatePersonalHousehold::class)->handle($eric);
        (require database_path('migrations/2026_09_25_000060_add_household_id_to_recipes_table.php'))->down();
        (require database_path('migrations/2026_09_25_000060_add_household_id_to_recipes_table.php'))->up();

        $this->assertSame($eric->fresh()->current_household_id, $recipe->fresh()->household_id);
    }

    public function test_an_invited_user_who_already_has_an_account_joins_and_switches_household(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $anna = $this->userWithHousehold('Anna Svensson');
        $annasOwn = $this->recipeBy($anna, 'Cinnamon buns');
        $erics = $this->recipeBy($eric, 'Pancakes');

        $invitation = $eric->currentTeam->teamInvitations()->create(['email' => $anna->email, 'role' => 'editor']);
        $url = URL::signedRoute('households.invitations.accept', ['invitation' => $invitation]);

        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs($anna)->get($url)->assertRedirect();

        $anna->refresh();
        $this->assertTrue($anna->belongsToTeam($eric->currentTeam));
        $this->assertSame($eric->current_household_id, $anna->current_household_id);
        $this->assertNotNull($anna->personalTeam(), 'keeps their own household');
        $this->assertModelMissing($invitation);
        $this->assertEqualsCanonicalizing(
            [$annasOwn->getKey(), $erics->getKey()],
            Recipe::accessibleTo($anna)->pluck('id')->all(),
        );
        $this->assertNotEmpty(session('filament.notifications'));
        $this->assertStringContainsString('The Krona household', json_encode(session('filament.notifications')));
    }

    public function test_an_invitation_cannot_be_accepted_from_another_account(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $anna = $this->userWithHousehold('Anna Svensson');
        $other = $this->userWithHousehold('Sam Stranger');

        $invitation = $eric->currentTeam->teamInvitations()->create(['email' => $anna->email, 'role' => 'editor']);

        $this->actingAs($other)
            ->get(URL::signedRoute('households.invitations.accept', ['invitation' => $invitation]))
            ->assertRedirect();

        $this->assertFalse($anna->fresh()->belongsToTeam($eric->currentTeam));
        $this->assertFalse($other->fresh()->belongsToTeam($eric->currentTeam));
        $this->assertModelExists($invitation);
    }

    public function test_the_invitation_email_links_to_panel_registration(): void
    {
        $eric = $this->userWithHousehold('Eric Krona');
        $invitation = $eric->currentTeam->teamInvitations()->create(['email' => 'new@example.com', 'role' => 'editor']);

        (new HouseholdInvitationMail($invitation))
            ->assertSeeInHtml(route('filament.app.auth.register'))
            ->assertSeeInHtml('The Krona household')
            ->assertSeeInHtml('/households/invitations/'.$invitation->getKey());
    }
}

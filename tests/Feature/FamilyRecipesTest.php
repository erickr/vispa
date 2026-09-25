<?php

namespace Tests\Feature;

use App\Actions\Families\CreatePersonalFamily;
use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Jetstream\Mail\TeamInvitation as TeamInvitationMail;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyRecipesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    private function userWithFamily(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(CreatePersonalFamily::class)->handle($user);

        return $user->fresh();
    }

    private function join(User $user, Team $family): User
    {
        $family->users()->attach($user, ['role' => 'editor']);

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

    public function test_a_new_recipe_goes_into_the_owners_current_family(): void
    {
        $eric = $this->userWithFamily('Eric Krona');

        $this->assertSame($eric->current_team_id, $this->recipeBy($eric, 'Pancakes')->team_id);
    }

    public function test_family_members_see_and_edit_each_others_recipes(): void
    {
        $eric = $this->userWithFamily('Eric Krona');
        $anna = $this->join($this->userWithFamily('Anna Svensson'), $eric->currentTeam);
        $stranger = $this->userWithFamily('Sam Stranger');

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

    public function test_a_recipe_can_be_created_in_another_family(): void
    {
        $eric = $this->userWithFamily('Eric Krona');
        $anna = $this->join($this->userWithFamily('Anna Svensson'), $eric->currentTeam);

        $this->actingAs($anna);
        Livewire::test(CreateRecipe::class)
            ->assertFormFieldIsVisible('team_id')
            ->fillForm(['team_id' => $eric->current_team_id, 'default_locale' => 'en', 'visibility' => 'private', 'title' => 'Waffles'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($eric->current_team_id, Recipe::latest('id')->first()->team_id);
    }

    public function test_a_recipe_cannot_be_put_into_someone_elses_family(): void
    {
        $anna = $this->userWithFamily('Anna Svensson');
        $this->join($anna, $this->userWithFamily('Eric Krona')->currentTeam);
        $stranger = $this->userWithFamily('Sam Stranger');

        $this->actingAs($anna->fresh());
        Livewire::test(CreateRecipe::class)
            ->fillForm(['team_id' => $stranger->current_team_id, 'default_locale' => 'en', 'visibility' => 'private'])
            ->call('create')
            ->assertHasFormErrors(['team_id']);
    }

    public function test_the_family_field_is_hidden_with_only_one_family(): void
    {
        $this->actingAs($this->userWithFamily('Eric Krona'));

        Livewire::test(CreateRecipe::class)->assertFormFieldIsHidden('team_id');
    }

    public function test_existing_recipes_move_into_their_owners_family(): void
    {
        $eric = User::factory()->create(['name' => 'Eric Krona']);
        $recipe = $this->recipeBy($eric, 'Pancakes');
        $this->assertNull($recipe->team_id);

        app(CreatePersonalFamily::class)->handle($eric);
        (require database_path('migrations/2026_09_25_000060_add_team_id_to_recipes_table.php'))->down();
        (require database_path('migrations/2026_09_25_000060_add_team_id_to_recipes_table.php'))->up();

        $this->assertSame($eric->fresh()->current_team_id, $recipe->fresh()->team_id);
    }

    public function test_an_invited_user_who_already_has_an_account_joins_and_switches_family(): void
    {
        Mail::fake();
        $eric = $this->userWithFamily('Eric Krona');
        $anna = $this->userWithFamily('Anna Svensson');
        $annasOwn = $this->recipeBy($anna, 'Cinnamon buns');
        $erics = $this->recipeBy($eric, 'Pancakes');

        $invitation = $eric->currentTeam->teamInvitations()->create(['email' => $anna->email, 'role' => 'editor']);
        $url = URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]);

        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs($anna)->get($url)->assertRedirect();

        $anna->refresh();
        $this->assertTrue($anna->belongsToTeam($eric->currentTeam));
        $this->assertSame($eric->current_team_id, $anna->current_team_id);
        $this->assertNotNull($anna->personalTeam(), 'keeps their own family');
        $this->assertModelMissing($invitation);
        $this->assertEqualsCanonicalizing(
            [$annasOwn->getKey(), $erics->getKey()],
            Recipe::accessibleTo($anna)->pluck('id')->all(),
        );
        $this->assertNotEmpty(session('filament.notifications'));
    }

    public function test_an_invitation_cannot_be_accepted_from_another_account(): void
    {
        $eric = $this->userWithFamily('Eric Krona');
        $anna = $this->userWithFamily('Anna Svensson');
        $other = $this->userWithFamily('Sam Stranger');

        $invitation = $eric->currentTeam->teamInvitations()->create(['email' => $anna->email, 'role' => 'editor']);

        $this->actingAs($other)
            ->get(URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]))
            ->assertRedirect();

        $this->assertFalse($anna->fresh()->belongsToTeam($eric->currentTeam));
        $this->assertFalse($other->fresh()->belongsToTeam($eric->currentTeam));
        $this->assertModelExists($invitation);
    }

    public function test_the_invitation_email_links_to_panel_registration(): void
    {
        $eric = $this->userWithFamily('Eric Krona');
        $invitation = $eric->currentTeam->teamInvitations()->create(['email' => 'new@example.com', 'role' => 'editor']);

        (new TeamInvitationMail($invitation))
            ->assertSeeInHtml(route('filament.app.auth.register'))
            ->assertSeeInHtml('The Krona family');
    }
}

<?php

namespace Tests\Feature;

use App\Actions\Families\CreatePersonalFamily;
use App\Filament\Pages\Family;
use App\Filament\Resources\Ingredients\Pages\EditIngredient;
use App\Filament\Resources\Ingredients\Pages\ListIngredients;
use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Two families, the Kronas (Eric and Anna) and the Does (Jane). Nothing one family owns — a
 * recipe, a revision, a private ingredient, the family page — is reachable from the other,
 * by listing, by URL, through a picker or by submitting an id the form never offered.
 */
class FamilyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $eric;

    private User $anna;

    private User $jane;

    private Recipe $kronaRecipe;

    private Recipe $doeRecipe;

    private Ingredient $kronaIngredient;

    private Ingredient $doeIngredient;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');

        $this->eric = $this->userWithFamily('Eric Krona');
        $this->anna = $this->userWithFamily('Anna Krona');
        $this->eric->currentTeam->users()->attach($this->anna);
        $this->anna = $this->anna->fresh();
        $this->jane = $this->userWithFamily('Jane Doe');

        $this->kronaRecipe = $this->recipeBy($this->anna, 'Krona pancakes', $this->eric->current_team_id);
        $this->doeRecipe = $this->recipeBy($this->jane, 'Doe waffles');

        $this->kronaIngredient = $this->ingredientBy($this->anna, 'krona spice mix');
        $this->doeIngredient = $this->ingredientBy($this->jane, 'doe spice mix');
    }

    private function userWithFamily(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(CreatePersonalFamily::class)->handle($user);

        return $user->fresh();
    }

    private function recipeBy(User $user, string $title, ?int $familyId = null): Recipe
    {
        $recipe = Recipe::create([
            'owner_user_id' => $user->getKey(),
            'team_id' => $familyId ?? $user->current_team_id,
            'default_locale' => 'en',
            'visibility' => 'private',
        ]);
        $recipe->revisions()->create([
            'locale' => 'en', 'version_number' => 1, 'status' => 'draft',
            'title' => $title, 'created_by_user_id' => $user->getKey(),
        ]);

        return $recipe;
    }

    private function ingredientBy(User $user, string $name): Ingredient
    {
        $ingredient = new Ingredient(['canonical_name' => $name]);
        $ingredient->owner_user_id = $user->getKey();
        $ingredient->save();

        return $ingredient;
    }

    private function revisionOf(Recipe $recipe): RecipeRevision
    {
        return $recipe->revisions()->firstOrFail();
    }

    /** @return array<string, array{string}> */
    public static function outsiders(): array
    {
        return ['a Doe looking at the Kronas' => ['jane'], 'a Krona looking at the Does' => ['eric']];
    }

    /** The other family's recipe and ingredient, from the point of view of $who. */
    private function theirs(string $who): array
    {
        return $who === 'jane'
            ? [$this->jane, $this->kronaRecipe, $this->kronaIngredient]
            : [$this->eric, $this->doeRecipe, $this->doeIngredient];
    }

    #[DataProvider('outsiders')]
    public function test_the_other_familys_recipes_are_not_listed(string $who): void
    {
        [$user, $recipe] = $this->theirs($who);
        $own = $who === 'jane' ? $this->doeRecipe : $this->kronaRecipe;

        $this->actingAs($user);

        Livewire::test(ListRecipes::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$recipe])
            // Search reaches through to revision titles; it must not reach across families.
            ->searchTable($who === 'jane' ? 'Krona' : 'Doe')
            ->assertCanNotSeeTableRecords([$recipe]);

        $this->assertFalse(Recipe::accessibleTo($user)->whereKey($recipe)->exists());
    }

    #[DataProvider('outsiders')]
    public function test_the_other_familys_recipe_pages_are_not_found(string $who): void
    {
        [$user, $recipe] = $this->theirs($who);
        $revision = $this->revisionOf($recipe);

        $this->actingAs($user);

        $this->get(RecipeResource::getUrl('edit', ['record' => $recipe]))->assertNotFound();
        $this->get(RecipeRevisionResource::getUrl('view', ['record' => $revision]))->assertNotFound();
        $this->get(RecipeRevisionResource::getUrl('edit', ['record' => $revision]))->assertNotFound();
    }

    #[DataProvider('outsiders')]
    public function test_the_other_familys_recipe_cannot_be_edited_directly(string $who): void
    {
        [$user, $recipe] = $this->theirs($who);
        $revision = $this->revisionOf($recipe);

        $this->actingAs($user);

        $this->assertThrows(fn () => Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->fillForm(['title' => 'Hijacked'])
            ->call('save'), ModelNotFoundException::class);

        $this->assertThrows(fn () => Livewire::test(EditRecipe::class, ['record' => $recipe->getKey()])
            ->fillForm(['visibility' => 'public'])
            ->call('save'), ModelNotFoundException::class);

        $this->assertNotSame('Hijacked', $revision->fresh()->title);
        $this->assertSame('private', $recipe->fresh()->visibility);
    }

    public function test_a_recipe_cannot_be_created_in_another_family(): void
    {
        $this->actingAs($this->jane);

        Livewire::test(CreateRecipe::class)
            ->fillForm(['team_id' => $this->eric->current_team_id, 'default_locale' => 'en', 'visibility' => 'private', 'title' => 'Planted'])
            ->call('create');

        $this->assertFalse(Recipe::where('team_id', $this->eric->current_team_id)->where('owner_user_id', $this->jane->id)->exists());
    }

    #[DataProvider('outsiders')]
    public function test_the_fork_pickers_do_not_offer_the_other_familys_recipes(string $who): void
    {
        [$user, $recipe] = $this->theirs($who);
        $revision = $this->revisionOf($recipe);

        $this->actingAs($user);

        Livewire::test(CreateRecipe::class)
            ->assertFormFieldExists('forked_from_recipe_id', fn (Select $field): bool => ! array_key_exists($recipe->getKey(), $field->getOptions()))
            ->assertFormFieldExists('forked_from_revision_id', fn (Select $field): bool => ! array_key_exists($revision->getKey(), $field->getOptions()))
            ->fillForm([
                'default_locale' => 'en',
                'visibility' => 'private',
                'forked_from_recipe_id' => $recipe->getKey(),
                'forked_from_revision_id' => $revision->getKey(),
            ])
            ->call('create')
            ->assertHasFormErrors(['forked_from_recipe_id', 'forked_from_revision_id']);
    }

    #[DataProvider('outsiders')]
    public function test_the_other_familys_ingredients_are_not_listed_or_editable(string $who): void
    {
        [$user, , $ingredient] = $this->theirs($who);

        $this->actingAs($user);

        Livewire::test(ListIngredients::class)->assertCanNotSeeTableRecords([$ingredient]);
        $this->get(EditIngredient::getUrl(['record' => $ingredient]))->assertNotFound();
        $this->assertFalse(Ingredient::visibleTo($user)->whereKey($ingredient)->exists());
        $this->assertFalse($user->can('update', $ingredient));
        $this->assertFalse($user->can('delete', $ingredient));
    }

    #[DataProvider('outsiders')]
    public function test_the_ingredient_picker_neither_offers_nor_accepts_the_other_familys_ingredients(string $who): void
    {
        [$user, , $ingredient] = $this->theirs($who);
        $ownRecipe = $who === 'jane' ? $this->doeRecipe : $this->kronaRecipe;
        $revision = $this->revisionOf($ownRecipe);

        $this->actingAs($user);

        Livewire::test(EditRecipeRevision::class, ['record' => $revision->getKey()])
            ->fillForm([
                'ingredientGroups' => [
                    ['title' => 'Mix', 'ingredients' => [
                        ['ingredient_id' => $ingredient->getKey(), 'quantity' => '1', 'unit_id' => null, 'optional' => false, 'preparation_note' => null],
                    ]],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertFalse($revision->ingredients()->where('ingredient_id', $ingredient->getKey())->exists());
    }

    public function test_family_members_do_share_with_each_other(): void
    {
        // The other side of the fence: within the Kronas everything is shared.
        $this->actingAs($this->eric);

        Livewire::test(ListRecipes::class)->assertCanSeeTableRecords([$this->kronaRecipe]);
        Livewire::test(ListIngredients::class)->assertCanSeeTableRecords([$this->kronaIngredient]);
        $this->get(RecipeRevisionResource::getUrl('edit', ['record' => $this->revisionOf($this->kronaRecipe)]))->assertOk();
    }

    public function test_the_family_page_only_shows_your_own_family(): void
    {
        $this->actingAs($this->jane);

        Livewire::test(Family::class)
            ->assertSee('The Doe family')
            ->assertDontSee('The Krona family')
            ->assertDontSee($this->eric->email)
            ->assertDontSee($this->anna->email);
    }

    public function test_you_cannot_switch_into_another_family(): void
    {
        $this->actingAs($this->jane);

        $this->assertFalse($this->jane->switchTeam($this->eric->currentTeam));

        // Jane has one family, so the switch action isn't offered; submitting it anyway fails.
        Livewire::test(Family::class)->assertActionHidden('switch');
        $this->assertSame($this->jane->personalTeam()->id, $this->jane->fresh()->current_team_id);
    }

    public function test_an_outsider_cannot_remove_members_or_cancel_invitations(): void
    {
        $invitation = $this->eric->currentTeam->teamInvitations()->create(['email' => 'new@example.com']);

        // Jane's page is about her own family: Anna is not among its members, so there is
        // nobody to remove, and the Kronas' invitation is not hers to cancel.
        $this->actingAs($this->jane);
        $this->assertThrows(
            fn () => Livewire::test(Family::class)->callAction('removeMember', arguments: ['user' => $this->anna->id]),
            ModelNotFoundException::class,
        );
        Livewire::test(Family::class)->callAction('cancelInvitation', arguments: ['invitation' => $invitation->id]);

        $this->assertTrue($this->anna->fresh()->belongsToTeam($this->eric->currentTeam));
        $this->assertModelExists($invitation);
    }

    public function test_leaving_a_family_takes_its_recipes_and_ingredients_away(): void
    {
        $ericsRecipe = $this->recipeBy($this->eric, 'Eric stew');
        $ericsIngredient = $this->ingredientBy($this->eric, 'eric spice');

        $this->eric->currentTeam->removeUser($this->anna);
        $anna = $this->anna->fresh();

        $this->assertFalse(Recipe::accessibleTo($anna)->whereKey($ericsRecipe)->exists());
        $this->assertFalse(Ingredient::visibleTo($anna)->whereKey($ericsIngredient)->exists());
        // What Anna wrote herself stays hers.
        $this->assertTrue(Recipe::accessibleTo($anna)->whereKey($this->kronaRecipe)->exists());
    }
}

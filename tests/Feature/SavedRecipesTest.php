<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Filament\Resources\RecipeRevisions\Pages\EditRecipeRevision;
use App\Filament\Resources\RecipeRevisions\Pages\ViewRecipeRevision;
use App\Filament\Resources\RecipeRevisions\RecipeRevisionResource;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeRevision;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Jane Doe publishes a recipe and shares it. Eric Krona saves it to his household, where he and
 * Anna can read it but not change it — until one of them makes a version of their own.
 */
class SavedRecipesTest extends TestCase
{
    use RefreshDatabase;

    private User $eric;

    private User $anna;

    private User $jane;

    private Recipe $waffles;

    private RecipeRevision $published;

    private RecipeRevision $draft;

    private Ingredient $janesSpice;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');

        $this->eric = $this->userWithHousehold('Eric Krona');
        $this->anna = $this->userWithHousehold('Anna Krona');
        $this->eric->currentTeam->users()->attach($this->anna);
        $this->anna = $this->anna->fresh();
        $this->jane = $this->userWithHousehold('Jane Doe');

        $this->janesSpice = new Ingredient(['canonical_name' => 'doe spice mix']);
        $this->janesSpice->owner_user_id = $this->jane->getKey();
        $this->janesSpice->save();
        $this->janesSpice->translations()->create(['locale' => 'sv', 'name' => 'doe kryddmix']);

        $this->waffles = Recipe::create([
            'owner_user_id' => $this->jane->getKey(),
            'default_locale' => 'en',
            'visibility' => 'public',
            'source_url' => 'https://example.com/waffles',
        ]);
        $this->published = $this->waffles->revisions()->create([
            'locale' => 'en', 'version_number' => 1, 'status' => 'published',
            'title' => 'Doe waffles', 'servings' => 4, 'created_by_user_id' => $this->jane->getKey(),
        ]);
        $this->published->ingredients()->create(['ingredient_id' => $this->janesSpice->getKey(), 'quantity' => 1, 'sort_order' => 1]);
        $this->published->instructionSteps()->create(['instruction_text' => 'Heat the iron.', 'sort_order' => 1]);
        // Jane's work in progress: never for anyone outside her household to see.
        $this->draft = $this->waffles->revisions()->create([
            'locale' => 'en', 'version_number' => 2, 'status' => 'draft',
            'title' => 'Secret waffles', 'created_by_user_id' => $this->jane->getKey(),
        ]);
    }

    private function userWithHousehold(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(CreatePersonalHousehold::class)->handle($user);

        return $user->fresh();
    }

    private function saveAsEric(): void
    {
        $this->actingAs($this->eric)->post(route('recipes.share.save', $this->waffles->uuid));
    }

    public function test_a_guest_is_asked_to_sign_in_to_save(): void
    {
        $this->get(route('recipes.share', $this->waffles->uuid))
            ->assertOk()
            ->assertSee(__('share.save.sign_in'))
            ->assertSee(route('recipes.share.sign-in', $this->waffles->uuid));

        // Signing in comes back to the recipe page, where the button now saves.
        $this->get(route('recipes.share.sign-in', $this->waffles->uuid))->assertRedirect(route('login'));
        $this->actingAs($this->eric)
            ->get(route('recipes.share.sign-in', $this->waffles->uuid))
            ->assertRedirect(route('recipes.share', $this->waffles->uuid));
    }

    public function test_the_share_page_offers_to_save_or_open(): void
    {
        $this->actingAs($this->eric)->get(route('recipes.share', $this->waffles->uuid))
            ->assertSee(__('share.save.action'))
            ->assertSee(route('recipes.share.save', $this->waffles->uuid));

        $this->saveAsEric();

        $this->actingAs($this->eric)->get(route('recipes.share', $this->waffles->uuid))
            ->assertDontSee(__('share.save.action'))
            ->assertSee(__('share.save.saved'));

        // Jane's own recipe needs no saving.
        $this->actingAs($this->jane)->get(route('recipes.share', $this->waffles->uuid))
            ->assertDontSee(__('share.save.action'))
            ->assertSee(__('share.save.own'));
    }

    public function test_a_recipe_with_nothing_published_offers_no_save(): void
    {
        $this->published->update(['status' => 'archived']);

        $this->actingAs($this->eric)->get(route('recipes.share', $this->waffles->uuid))
            ->assertOk()
            ->assertDontSee(__('share.save.action'));

        $this->actingAs($this->eric)->post(route('recipes.share.save', $this->waffles->uuid))->assertNotFound();
        $this->assertFalse($this->waffles->savedByHouseholds()->exists());
    }

    public function test_a_private_recipe_cannot_be_saved(): void
    {
        $this->waffles->update(['visibility' => 'private']);

        $this->actingAs($this->eric)->post(route('recipes.share.save', $this->waffles->uuid))->assertNotFound();
        $this->assertFalse($this->waffles->savedByHouseholds()->exists());
    }

    public function test_saving_puts_it_in_the_current_household_and_opens_the_published_version(): void
    {
        $this->actingAs($this->eric)
            ->post(route('recipes.share.save', $this->waffles->uuid))
            ->assertRedirect(RecipeRevisionResource::getUrl('view', ['record' => $this->published]));

        $this->assertTrue($this->eric->currentTeam->savedRecipes()->whereKey($this->waffles)->exists());

        // Twice is still once.
        $this->saveAsEric();
        $this->assertSame(1, $this->waffles->savedByHouseholds()->count());

        // It stays Jane's.
        $this->assertSame($this->jane->getKey(), $this->waffles->fresh()->owner_user_id);
        $this->assertSame($this->jane->current_household_id, $this->waffles->fresh()->household_id);
    }

    public function test_saving_your_own_recipe_does_nothing(): void
    {
        $this->actingAs($this->jane)->post(route('recipes.share.save', $this->waffles->uuid))->assertRedirect();

        $this->assertFalse($this->waffles->savedByHouseholds()->exists());
    }

    public function test_the_whole_household_sees_the_saved_recipe_by_its_published_title(): void
    {
        $this->saveAsEric();

        foreach ([$this->eric, $this->anna] as $member) {
            $this->actingAs($member);

            Livewire::test(ListRecipes::class)
                ->assertCanSeeTableRecords([$this->waffles])
                ->assertSee('Doe waffles')
                ->assertDontSee('Secret waffles')
                ->assertSee(__('recipe.saved.badge'))
                // The owners' draft titles are not searchable from outside.
                ->searchTable('Secret')
                ->assertCanNotSeeTableRecords([$this->waffles]);
        }
    }

    public function test_a_saved_recipe_can_be_read_but_not_changed(): void
    {
        $this->saveAsEric();
        $this->actingAs($this->anna);

        $this->get(RecipeRevisionResource::getUrl('view', ['record' => $this->published]))
            ->assertOk()
            ->assertSee(__('recipe.saved.callout_heading'))
            ->assertDontSee('Secret waffles');

        Livewire::test(ViewRecipeRevision::class, ['record' => $this->published->getKey()])
            ->assertActionHidden('edit')
            ->assertActionHidden('settings')
            ->assertActionHidden('share')
            ->assertActionVisible('fork')
            ->assertActionVisible('unsave');

        $this->get(RecipeRevisionResource::getUrl('edit', ['record' => $this->published]))->assertForbidden();
        $this->get(RecipeResource::getUrl('edit', ['record' => $this->waffles]))->assertForbidden();
        // The draft is not part of what was saved.
        $this->get(RecipeRevisionResource::getUrl('view', ['record' => $this->draft]))->assertNotFound();

        $this->assertFalse($this->anna->can('update', $this->published));
        $this->assertFalse($this->anna->can('delete', $this->waffles));
    }

    public function test_bulk_delete_leaves_a_saved_recipe_alone(): void
    {
        $this->saveAsEric();
        $this->actingAs($this->eric);

        Livewire::test(ListRecipes::class)->callTableBulkAction('delete', [$this->waffles]);

        $this->assertModelExists($this->waffles);
    }

    public function test_it_disappears_when_the_owners_make_it_private(): void
    {
        $this->saveAsEric();
        $this->waffles->update(['visibility' => 'private']);
        $this->actingAs($this->eric);

        Livewire::test(ListRecipes::class)->assertCanNotSeeTableRecords([$this->waffles]);
        $this->get(RecipeRevisionResource::getUrl('view', ['record' => $this->published]))->assertNotFound();
    }

    public function test_it_can_be_removed_again(): void
    {
        $this->saveAsEric();
        $this->actingAs($this->anna);

        Livewire::test(ViewRecipeRevision::class, ['record' => $this->published->getKey()])
            ->callAction('unsave')
            ->assertRedirect(RecipeResource::getUrl('index'));

        $this->assertFalse($this->waffles->savedByHouseholds()->exists());
        $this->assertModelExists($this->waffles);
    }

    public function test_making_your_own_version_forks_it_into_your_household(): void
    {
        $this->saveAsEric();
        $this->actingAs($this->anna);

        Livewire::test(ViewRecipeRevision::class, ['record' => $this->published->getKey()])
            ->callAction('fork');

        $fork = Recipe::where('forked_from_recipe_id', $this->waffles->getKey())->sole();
        $copy = $fork->revisions()->sole();

        $this->assertSame($this->anna->getKey(), $fork->owner_user_id);
        $this->assertSame($this->eric->current_household_id, $fork->household_id);
        $this->assertSame($this->published->getKey(), $fork->forked_from_revision_id);
        $this->assertSame('private', $fork->visibility);
        $this->assertSame('https://example.com/waffles', $fork->source_url);

        // A draft copy of the published version, not of Jane's draft.
        $this->assertSame('Doe waffles', $copy->title);
        $this->assertSame('draft', $copy->status);
        $this->assertSame(1, $copy->version_number);
        $this->assertSame(4, $copy->servings);
        $this->assertSame('Heat the iron.', $copy->instructionSteps()->sole()->instruction_text);

        // Jane's private ingredient is swapped for a copy the Kronas own.
        $ingredient = $copy->ingredients()->sole()->ingredient;
        $this->assertNotSame($this->janesSpice->getKey(), $ingredient->getKey());
        $this->assertSame('doe spice mix', $ingredient->canonical_name);
        $this->assertSame($this->anna->getKey(), $ingredient->owner_user_id);
        $this->assertSame('doe kryddmix', $ingredient->translations()->sole()->name);

        // Theirs is untouched; hers is hers to edit.
        $this->assertSame(2, $this->waffles->revisions()->count());
        $this->assertSame($this->janesSpice->getKey(), $this->published->ingredients()->sole()->ingredient_id);
        $this->assertTrue($this->anna->can('update', $copy));
        $this->assertTrue($this->eric->can('update', $copy));

        Livewire::test(EditRecipeRevision::class, ['record' => $copy->getKey()])
            ->fillForm(['title' => 'Krona waffles'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Krona waffles', $copy->fresh()->title);
        $this->assertSame('Doe waffles', $this->published->fresh()->title);

        // The fork points back at what it started from.
        $this->get(RecipeRevisionResource::getUrl('view', ['record' => $copy]))
            ->assertOk()
            ->assertSee(__('recipe.saved.based_on', ['title' => 'Doe waffles']), escape: false);
    }

    public function test_once_forked_the_saved_original_gives_its_place_to_your_version(): void
    {
        $this->saveAsEric();
        $this->actingAs($this->anna);

        Livewire::test(ViewRecipeRevision::class, ['record' => $this->published->getKey()])->callAction('fork');
        $fork = Recipe::where('forked_from_recipe_id', $this->waffles->getKey())->sole();

        // The whole household sees their version in its place, the filter included.
        foreach ([$this->anna, $this->eric] as $member) {
            $this->actingAs($member);

            Livewire::test(ListRecipes::class)
                ->assertCanSeeTableRecords([$fork])
                ->assertCanNotSeeTableRecords([$this->waffles])
                ->filterTable('saved_from_others')
                ->assertCanNotSeeTableRecords([$this->waffles]);
        }

        // Hidden, not removed: the fork's link back to the original still opens.
        $this->assertTrue($this->waffles->savedByHouseholds()->exists());
        $this->get(RecipeRevisionResource::getUrl('view', ['record' => $this->published]))->assertOk();

        // And the shared page opens their version rather than offering to save again.
        $this->get(route('recipes.share', $this->waffles->uuid))
            ->assertDontSee(__('share.save.action'))
            ->assertSee(__('share.save.forked'))
            ->assertSee(RecipeRevisionResource::getUrl('view', ['record' => $fork->revisions()->sole()]), escape: false);
    }

    public function test_the_owners_still_see_their_recipe_after_someone_forks_it(): void
    {
        $this->saveAsEric();
        $this->actingAs($this->eric);
        Livewire::test(ViewRecipeRevision::class, ['record' => $this->published->getKey()])->callAction('fork');

        $this->actingAs($this->jane);
        Livewire::test(ListRecipes::class)->assertCanSeeTableRecords([$this->waffles]);
    }

    public function test_a_fork_reuses_an_ingredient_the_household_already_has(): void
    {
        $ours = new Ingredient(['canonical_name' => 'Doe Spice Mix']);
        $ours->owner_user_id = $this->eric->getKey();
        $ours->save();

        $this->saveAsEric();
        $this->actingAs($this->anna);

        Livewire::test(ViewRecipeRevision::class, ['record' => $this->published->getKey()])->callAction('fork');

        $copy = Recipe::where('forked_from_recipe_id', $this->waffles->getKey())->sole()->revisions()->sole();
        $this->assertSame($ours->getKey(), $copy->ingredients()->sole()->ingredient_id);
    }

    public function test_an_unsaved_recipe_cannot_be_forked_or_read_in_the_panel(): void
    {
        $this->actingAs($this->eric);

        $this->get(RecipeRevisionResource::getUrl('view', ['record' => $this->published]))->assertNotFound();
        Livewire::test(ListRecipes::class)->assertCanNotSeeTableRecords([$this->waffles]);
        $this->assertFalse($this->eric->can('view', $this->published));
    }
}

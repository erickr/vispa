<?php

namespace Tests\Feature;

use App\Actions\Households\CreatePersonalHousehold;
use App\Filament\Resources\Ingredients\Pages\CreateIngredient;
use App\Filament\Resources\Ingredients\Pages\EditIngredient;
use App\Filament\Resources\Ingredients\Pages\ListIngredients;
use App\Models\Ingredient;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Anyone can add ingredients, and what they add belongs to them and their household. The catalog
 * admin's ingredients are shared with everyone and only the admin edits them.
 */
class IngredientAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');

        $this->admin = User::factory()->create(['email' => User::CATALOG_ADMIN_EMAIL]);
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
    }

    private function createAs(User $user, string $name): Ingredient
    {
        $this->actingAs($user);

        Livewire::test(CreateIngredient::class)
            ->fillForm(['canonical_name' => $name])
            ->call('create')
            ->assertHasNoFormErrors();

        return Ingredient::where('canonical_name', $name)->latest('id')->firstOrFail();
    }

    public function test_the_admins_ingredients_are_shared_and_users_are_private(): void
    {
        $salt = $this->createAs($this->admin, 'salt');
        $gochujang = $this->createAs($this->alice, 'gochujang');

        $this->assertTrue($salt->isShared());
        $this->assertSame($this->alice->id, $gochujang->owner_user_id);

        $this->actingAs($this->alice);
        Livewire::test(ListIngredients::class)->assertCanSeeTableRecords([$salt, $gochujang]);

        $this->actingAs($this->bob);
        Livewire::test(ListIngredients::class)
            ->assertCanSeeTableRecords([$salt])
            ->assertCanNotSeeTableRecords([$gochujang]);
        $this->get(EditIngredient::getUrl(['record' => $gochujang]))->assertNotFound();
    }

    public function test_only_the_admin_edits_shared_ingredients(): void
    {
        $salt = $this->createAs($this->admin, 'salt');
        $gochujang = $this->createAs($this->alice, 'gochujang');

        $this->actingAs($this->alice);
        Livewire::test(ListIngredients::class)
            ->assertTableActionHidden('edit', $salt)
            ->assertTableActionVisible('edit', $gochujang);
        $this->get(EditIngredient::getUrl(['record' => $salt]))->assertForbidden();

        // Bulk delete skips the shared row the user may not touch.
        Livewire::test(ListIngredients::class)
            ->callTableBulkAction('delete', [$salt, $gochujang]);
        $this->assertModelExists($salt);
        $this->assertModelMissing($gochujang);

        $this->actingAs($this->admin);
        Livewire::test(EditIngredient::class, ['record' => $salt->getRouteKey()])
            ->fillForm(['canonical_name' => 'sea salt'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('sea salt', $salt->fresh()->canonical_name);
    }

    public function test_names_are_unique_within_what_a_user_can_see(): void
    {
        $this->createAs($this->admin, 'salt');
        $this->createAs($this->alice, 'gochujang');

        // Clashes with the shared catalog.
        $this->actingAs($this->bob);
        Livewire::test(CreateIngredient::class)
            ->fillForm(['canonical_name' => 'salt'])
            ->call('create')
            ->assertHasFormErrors(['canonical_name' => 'unique']);

        // Alice's private ingredient doesn't block Bob from making his own.
        $this->createAs($this->bob, 'gochujang');
        $this->assertSame(2, Ingredient::where('canonical_name', 'gochujang')->count());
    }

    /** Carol joins Alice's household; Bob stays outside it. */
    private function carolInAlicesHousehold(): User
    {
        $household = app(CreatePersonalHousehold::class)->handle($this->alice);
        $carol = User::factory()->create();
        app(CreatePersonalHousehold::class)->handle($carol);
        $household->users()->attach($carol, ['role' => 'editor']);

        return $carol->fresh();
    }

    public function test_household_members_see_and_edit_each_others_ingredients(): void
    {
        $carol = $this->carolInAlicesHousehold();
        $gochujang = $this->createAs($this->alice, 'gochujang');

        $this->actingAs($carol);
        Livewire::test(ListIngredients::class)
            ->assertCanSeeTableRecords([$gochujang])
            ->assertTableActionVisible('edit', $gochujang);
        Livewire::test(EditIngredient::class, ['record' => $gochujang->getRouteKey()])
            ->fillForm(['canonical_name' => 'gochujang paste'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('gochujang paste', $gochujang->fresh()->canonical_name);
        $this->assertTrue(Ingredient::visibleTo($carol)->whereKey($gochujang)->exists());

        $this->actingAs($this->bob);
        Livewire::test(ListIngredients::class)->assertCanNotSeeTableRecords([$gochujang]);
        $this->get(EditIngredient::getUrl(['record' => $gochujang]))->assertNotFound();
    }

    public function test_names_are_unique_within_the_household(): void
    {
        $carol = $this->carolInAlicesHousehold();
        $this->createAs($this->alice, 'gochujang');

        $this->actingAs($carol);
        Livewire::test(CreateIngredient::class)
            ->fillForm(['canonical_name' => 'gochujang'])
            ->call('create')
            ->assertHasFormErrors(['canonical_name' => 'unique']);
    }
}

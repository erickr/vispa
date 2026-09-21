<?php

namespace Tests\Feature;

use App\Filament\Resources\Units\Pages\CreateUnit;
use App\Filament\Resources\Units\Pages\EditUnit;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Units are shared by every recipe, so everyone can read the list but only the units admin edits it.
 */
class UnitAccessTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');

        $this->unit = Unit::create(['code' => 'test-unit', 'type' => 'mass', 'factor_to_base' => 1]);
    }

    public function test_the_admin_can_create_edit_and_delete_units(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'EK@itomat.se']));

        Livewire::test(ListUnits::class)
            ->assertOk()
            ->assertActionVisible('create')
            ->assertTableActionVisible('edit', $this->unit);

        Livewire::test(CreateUnit::class)->assertOk();

        Livewire::test(EditUnit::class, ['record' => $this->unit->getRouteKey()])
            ->assertOk()
            ->assertActionVisible('delete');
    }

    public function test_other_users_can_only_browse_units(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']));

        Livewire::test(ListUnits::class)
            ->assertOk()
            ->searchTable('test-unit')
            ->assertCanSeeTableRecords([$this->unit])
            ->assertActionHidden('create')
            ->assertTableActionHidden('edit', $this->unit)
            ->assertTableBulkActionHidden('delete');

        $this->get(CreateUnit::getUrl())->assertForbidden();
        $this->get(EditUnit::getUrl(['record' => $this->unit]))->assertForbidden();
    }
}

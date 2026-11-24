<?php

namespace Tests\Feature;

use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Filament\Resources\Branches\RelationManagers\AreasRelationManager;
use App\Models\Area;
use App\Models\Branch;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BranchAreasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_add_delivery_areas_to_a_branch(): void
    {
        $this->actingAs(User::where('role', 'admin')->first());
        $branch = Branch::first();
        $area = Area::whereNotIn('id', $branch->areas()->pluck('areas.id'))->firstOrFail();

        Livewire::test(AreasRelationManager::class, ['ownerRecord' => $branch, 'pageClass' => EditBranch::class])
            ->callAction(TestAction::make('attach')->table(), ['recordId' => [$area->id], 'delivery_fee' => 15, 'min_order' => 100])
            ->assertHasNoFormErrors();

        $pivot = $branch->areas()->whereKey($area->id)->first()?->pivot;
        $this->assertNotNull($pivot);
        $this->assertEquals(15, $pivot->delivery_fee);
    }
}

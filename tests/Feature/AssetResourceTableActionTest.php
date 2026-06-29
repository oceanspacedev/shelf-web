<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Models\User;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssetResourceTableActionTest extends TestCase
{
    public function test_damaged_pending_nbh_asset_has_complete_repair_action(): void
    {
        $table = AssetResource::table(Table::make(Mockery::mock(HasTable::class)));

        $this->assertTrue($table->hasAction('completeRepair'));

        $action = $table->getFlatActions()['completeRepair'];

        $asset = new Asset([
            'condition_status' => AssetCondition::Damaged,
            'nbh_status' => NbhStatus::Pending,
        ]);

        // completeRepair hanya boleh dijalankan general_affair/super_admin.
        Role::firstOrCreate(['name' => 'general_affair', 'guard_name' => 'web']);
        $generalAffair = User::create(['name' => 'GA']);
        $generalAffair->assignRole('general_affair');

        $this->actingAs($generalAffair);
        $this->assertTrue($action->record($asset)->isVisible());

        // Pengguna tanpa peran terkait tidak melihat aksi.
        $plain = User::create(['name' => 'Staff']);
        $this->actingAs($plain);
        $this->assertFalse($action->record($asset)->isVisible());
    }
}

<?php

namespace Tests\Support;

use App\Models\Asset;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class AssetQrLabelTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'app.url' => 'https://shelf.example.test',
            'filesystems.default' => 'local',
        ]);
        DB::purge('sqlite');
        Storage::fake('local');

        Schema::create('asset_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('asset_location_id')->nullable()->constrained();
            $table->timestamps();
        });
        Schema::create('asset_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->string('stored_path');
        });

        foreach ([
            '2014_10_12_000000_create_users_table',
            '2024_07_08_110142_create_permission_tables',
            '2026_09_23_150000_create_asset_qrs_tables',
            '2026_09_24_000001_create_asset_qr_label_histories_table',
            '2026_09_24_000002_batch_asset_qr_label_histories',
            '2026_09_24_000003_add_file_to_asset_qr_label_histories',
            '2026_09_24_000004_add_storage_disks_to_asset_files',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }

        $permission = Permission::create(['name' => 'view_asset', 'guard_name' => 'web']);
        Role::create(['name' => 'super_admin', 'guard_name' => 'web'])->givePermissionTo($permission);
    }

    /** @return Collection<int, Asset> */
    protected function createAssets(int $count, string $prefix = 'Label Asset'): Collection
    {
        return collect(range(1, $count))
            ->map(fn (int $index): Asset => Asset::create(['name' => $prefix.' '.$index]));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migrasi 2026_10_08_000007: user panel yang sudah ada ditandai "akses semua
 * badan usaha" supaya tidak kehilangan data (termasuk badan usaha yang dibuat
 * nanti) saat pembatasan per badan usaha diaktifkan.
 */
class BusinessEntityAccessFlagMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('business_entity_id')->nullable();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
    }

    public function test_existing_panel_users_get_every_business_entity_and_super_admin_is_skipped(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Super Admin'],
            ['id' => 2, 'name' => 'Admin'],
            ['id' => 3, 'name' => 'GA'],
            ['id' => 4, 'name' => 'Karyawan tanpa role'],
        ]);
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'super_admin', 'guard_name' => 'web'],
            ['id' => 2, 'name' => 'admin', 'guard_name' => 'web'],
            ['id' => 3, 'name' => 'general_affair', 'guard_name' => 'web'],
        ]);
        DB::table('model_has_roles')->insert([
            ['role_id' => 1, 'model_type' => User::class, 'model_id' => 1],
            ['role_id' => 2, 'model_type' => User::class, 'model_id' => 2],
            ['role_id' => 3, 'model_type' => User::class, 'model_id' => 3],
            ['role_id' => 2, 'model_type' => User::class, 'model_id' => 3],
        ]);

        $migration = require database_path('migrations/2026_10_08_000007_add_access_all_business_entities_to_users_table.php');
        $migration->up();
        $migration->up(); // idempoten

        $this->assertSame([
            1 => false, // super admin memang tanpa batasan, tidak perlu flag
            2 => true,
            3 => true,
            4 => false, // user tanpa role bukan user panel
        ], DB::table('users')->orderBy('id')->pluck('access_all_business_entities', 'id')->map(fn ($flag): bool => (bool) $flag)->all());

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'access_all_business_entities'));
    }
}

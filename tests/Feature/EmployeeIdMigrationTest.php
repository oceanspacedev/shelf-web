<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeIdMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('whatsapp_number')->nullable();
        });
    }

    public function test_migration_adds_a_unique_nullable_employee_id(): void
    {
        $migration = require database_path('migrations/2026_09_24_120000_add_employee_id_to_users_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'employee_id'));
        DB::table('users')->insert(['name' => 'Tanpa ID', 'employee_id' => null]);
        DB::table('users')->insert(['name' => 'Dengan ID', 'employee_id' => '2024.08.15.03']);

        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'Duplikat', 'employee_id' => '2024.08.15.03']);
    }
}

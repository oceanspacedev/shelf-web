<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappLoginNumberMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('whatsapp_number')->nullable();
        });
    }

    public function test_migration_does_not_promote_existing_public_contacts_to_credentials_and_can_roll_back(): void
    {
        DB::table('users')->insert([
            ['whatsapp_number' => '081234567890'],
            ['whatsapp_number' => '+62 812-3456-7890'],
            ['whatsapp_number' => null],
        ]);
        $migration = require database_path('migrations/2026_09_24_000000_add_whatsapp_login_number_to_users_table.php');
        $migration->up();

        $this->assertSame([null, null, null], DB::table('users')->pluck('whatsapp_login_number')->all());

        $migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'whatsapp_login_number'));
        $this->assertSame(['081234567890', '+62 812-3456-7890', null], DB::table('users')->pluck('whatsapp_number')->all());
    }

    public function test_a_login_number_cannot_be_assigned_to_multiple_accounts(): void
    {
        $migration = require database_path('migrations/2026_09_24_000000_add_whatsapp_login_number_to_users_table.php');
        $migration->up();
        DB::table('users')->insert(['whatsapp_login_number' => '6281234567890']);

        $this->expectException(QueryException::class);
        DB::table('users')->insert(['whatsapp_login_number' => '6281234567890']);
    }
}

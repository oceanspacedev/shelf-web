<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssetTransferDocumentTypeMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
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
        Schema::create('asset_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->string('letter_number')->nullable();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->timestamps();
        });
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('condition_status');
            $table->unsignedBigInteger('recipient_id')->nullable();
        });
        Schema::create('asset_transfer_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_transfer_id');
            $table->unsignedBigInteger('asset_id');
        });
    }

    public function test_legacy_transfers_get_their_document_type_from_the_old_role_rules(): void
    {
        DB::table('users')->insert([
            ['id' => 2, 'name' => 'GA', 'username' => 'adminga'],
            ['id' => 3, 'name' => 'Staf GA', 'username' => null],
            ['id' => 4, 'name' => 'Karyawan', 'username' => null],
            ['id' => 5, 'name' => 'Karyawan Lain', 'username' => null],
        ]);
        DB::table('roles')->insert(['id' => 1, 'name' => 'general_affair', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert([
            ['role_id' => 1, 'model_type' => User::class, 'model_id' => 2],
            ['role_id' => 1, 'model_type' => User::class, 'model_id' => 3],
        ]);
        DB::table('asset_transfers')->insert([
            ['id' => 1, 'from_user_id' => 2, 'to_user_id' => 4], // akun GA -> karyawan
            ['id' => 2, 'from_user_id' => 4, 'to_user_id' => 5], // karyawan -> karyawan
            ['id' => 3, 'from_user_id' => 5, 'to_user_id' => 2], // karyawan -> akun GA
            ['id' => 4, 'from_user_id' => 2, 'to_user_id' => 3], // akun GA -> staf GA (aturan lama: serah terima)
            ['id' => 5, 'from_user_id' => 3, 'to_user_id' => 2], // staf GA -> akun GA (aturan lama: pengembalian)
            ['id' => 6, 'from_user_id' => 3, 'to_user_id' => 4], // staf GA -> karyawan
        ]);

        $migration = require database_path('migrations/2026_10_08_000005_add_document_type_to_asset_transfers_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('asset_transfers', 'document_type'));
        $this->assertSame([
            1 => 'serah_terima',
            2 => 'pengalihan_barang',
            3 => 'pengembalian_barang',
            4 => 'serah_terima',
            5 => 'pengembalian_barang',
            6 => 'serah_terima',
        ], DB::table('asset_transfers')->orderBy('id')->pluck('document_type', 'id')->all());

        // Menjalankan ulang tidak mengubah apa pun.
        $migration->up();
        $this->assertSame(6, DB::table('asset_transfers')->whereNotNull('document_type')->count());
    }

    public function test_available_assets_lose_their_holder_and_become_stock(): void
    {
        Schema::table('asset_transfers', function (Blueprint $table): void {
            $table->string('document_type', 32)->nullable();
        });

        DB::table('assets')->insert([
            ['id' => 1, 'name' => 'Stok di akun GA', 'condition_status' => 'available', 'recipient_id' => 2],
            ['id' => 2, 'name' => 'Dipakai karyawan', 'condition_status' => 'transferred', 'recipient_id' => 4],
            ['id' => 3, 'name' => 'Stok', 'condition_status' => 'available', 'recipient_id' => null],
            ['id' => 4, 'name' => 'Dikembalikan lalu rusak', 'condition_status' => 'damaged', 'recipient_id' => 2],
            ['id' => 5, 'name' => 'Rusak di tangan karyawan', 'condition_status' => 'damaged', 'recipient_id' => 4],
        ]);
        DB::table('asset_transfers')->insert([
            ['id' => 1, 'document_type' => 'serah_terima', 'from_user_id' => 2, 'to_user_id' => 4],
            ['id' => 2, 'document_type' => 'pengembalian_barang', 'from_user_id' => 4, 'to_user_id' => 2],
        ]);
        DB::table('asset_transfer_details')->insert([
            ['id' => 1, 'asset_transfer_id' => 1, 'asset_id' => 4], // dulu diserahkan ...
            ['id' => 2, 'asset_transfer_id' => 2, 'asset_id' => 4], // ... lalu dikembalikan ke akun GA
            ['id' => 3, 'asset_transfer_id' => 1, 'asset_id' => 5], // masih di tangan karyawan
        ]);

        $migration = require database_path('migrations/2026_10_08_000006_move_available_assets_to_stock.php');
        $migration->up();

        $this->assertNull(DB::table('assets')->find(1)->recipient_id);
        $this->assertSame(4, (int) DB::table('assets')->find(2)->recipient_id);
        $this->assertNull(DB::table('assets')->find(3)->recipient_id);
        $this->assertNull(DB::table('assets')->find(4)->recipient_id, 'Aset yang BA terakhirnya pengembalian kembali ke stok walau rusak.');
        $this->assertSame(4, (int) DB::table('assets')->find(5)->recipient_id, 'Aset rusak di tangan karyawan tetap dipegang karyawan.');
    }

    public function test_incident_assets_held_by_general_affair_staff_return_to_stock(): void
    {
        Schema::table('asset_transfers', function (Blueprint $table): void {
            $table->string('document_type', 32)->nullable();
        });

        DB::table('roles')->insert(['id' => 1, 'name' => 'general_affair', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert([
            ['role_id' => 1, 'model_type' => User::class, 'model_id' => 2],
            ['role_id' => 1, 'model_type' => User::class, 'model_id' => 3],
        ]);
        DB::table('assets')->insert([
            ['id' => 1, 'name' => 'Hilang di gudang GA', 'condition_status' => 'lost', 'recipient_id' => 3],
            ['id' => 2, 'name' => 'Rusak di akun GA', 'condition_status' => 'damaged', 'recipient_id' => 2],
            ['id' => 3, 'name' => 'Rusak di tangan karyawan', 'condition_status' => 'damaged', 'recipient_id' => 4],
            ['id' => 4, 'name' => 'Dipakai staf GA', 'condition_status' => 'transferred', 'recipient_id' => 3],
        ]);

        $migration = require database_path('migrations/2026_10_08_000006_move_available_assets_to_stock.php');
        $migration->up();

        $this->assertNull(DB::table('assets')->find(1)->recipient_id);
        $this->assertNull(DB::table('assets')->find(2)->recipient_id);
        $this->assertSame(4, (int) DB::table('assets')->find(3)->recipient_id, 'Insiden di tangan karyawan tetap dipegang karyawan.');
        $this->assertSame(3, (int) DB::table('assets')->find(4)->recipient_id, 'Aset yang dipakai staf GA sendiri tidak dianggap stok.');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('asset_locations')->insert([
            [
                'name' => 'Kantor Pusat',
                'address' => 'Jl. Jendral Sudirman No. 123, Jakarta',
                'description' => 'Kantor pusat perusahaan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gudang Utama',
                'address' => 'Jl. Industri Raya No. 5, Bekasi',
                'description' => 'Gudang penyimpanan barang',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cabang Bandung',
                'address' => 'Jl. Braga No. 90, Bandung',
                'description' => 'Kantor cabang di Bandung',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Tambahkan lokasi aset lainnya di sini sesuai kebutuhan
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Names must match config/vehicle-asset-reconciliation.php location_aliases targets
     * (exact after normalize) so local/staging validate-masters passes.
     */
    public function run(): void
    {
        $now = now();

        DB::table('asset_locations')->insert([
            [
                'name' => 'HEAD OFFICE PIK',
                'address' => 'PIK, Jakarta',
                'description' => 'Head Office PIK (alias HO/PIK)',
                'external_code' => 'ho',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'GUDANG PRIMA CENTER',
                'address' => 'Prima Center',
                'description' => 'Gudang Prima Center (alias PC)',
                'external_code' => 'pc',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'PURWOKERTO',
                'address' => 'Purwokerto',
                'description' => 'Lokasi Purwokerto',
                'external_code' => 'purwokerto',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'AUTO EV JATIWANGI',
                'address' => 'Jatiwangi',
                'description' => 'Auto EV Jatiwangi (alias JATIWANGI)',
                'external_code' => 'jatiwangi',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}

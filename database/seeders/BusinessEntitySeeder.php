<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessEntitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Names must match config/vehicle-asset-reconciliation.php alias targets exactly
     * (and production master names). Short codes remain as ACC aliases in config.
     */
    public function run(): void
    {
        $now = now();

        DB::table('business_entities')->insert([
            ['name' => 'PT MEDIA SELULAR INDONESIA', 'format' => '120920.MSI/', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CV COMPLETE SELULAR', 'format' => '221218.CS/', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CV TOP SELULAR', 'format' => '191415.TOP/', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PT. MAJU KENDARAAN LISTRIK INDONESIA', 'format' => '1210118.MKLI/', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PT RETAIL INDONESIA SELALU MAJU', 'format' => '1781812.RISM/', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CV BERSAMA CS', 'format' => '221218.BCS/', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CV MAJU TECNOLOGI', 'format' => '120920.MT/', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}

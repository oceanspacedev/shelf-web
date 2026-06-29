<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessEntitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('business_entities')->insert([
            ['name' => 'MAJU', 'format' => '120920.MT/'],
            ['name' => 'MKLI', 'format' => '1210118.MKLI/'],
            ['name' => 'CV.CS', 'format' => '221218.CS/'],
            ['name' => 'TOP', 'format' => '191415.TOP/'],
            ['name' => 'RISM', 'format' => '1781812.RISM/'],
        ]);
    }
}

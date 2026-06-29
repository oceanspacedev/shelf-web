<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('brands')->insert([
            // Merek Elektronik
            ['name' => 'Apple', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Samsung', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sony', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Lenovo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'HP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Dell', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Asus', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Acer', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Xiaomi', 'created_at' => now(), 'updated_at' => now()],

            // Merek Perabot Kantor
            ['name' => 'IKEA', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Informa', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chitose', 'created_at' => now(), 'updated_at' => now()],

            // Merek Kendaraan
            ['name' => 'Toyota', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Honda', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Daihatsu', 'created_at' => now(), 'updated_at' => now()],

            // Merek Lainnya (Sesuaikan dengan kebutuhan)
            ['name' => 'Sharp', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Panasonic', 'created_at' => now(), 'updated_at' => now()],
            // ... tambahkan merek lainnya di sini
        ]);
    }
}

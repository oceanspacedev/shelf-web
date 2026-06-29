<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'FURNITURE' => ['MEJA', 'KURSI', 'SPREI', 'SELIMUT', 'BEAN BAG', 'FIGURA', 'CERMIN', 'KARPET', 'KOMPOR', 'LEMARI', 'PAPAN TULIS', 'RAK-RAKAN', 'SOFA'],
            'PERKAKAS' => ['OBENG', 'TOOLBOX', 'TALANG AC', 'BRANKAS', 'TABUNG GAS 12KG', 'TIMBANGAN', 'TANGGA'],
            'BARANG ELEKTRONIK' => ['LAPTOP', 'HANDPHONE', 'AC', 'KIPAS ANGIN', 'DISPENSER', 'KULKAS', 'KAMERA CCTV', 'PRINTER', 'ROUTER WIFI', 'MOUSE', 'BARCODE SCANNER', 'SCANNER BARCODE BLUETOOTH', 'POINTER PRESENTASI', 'KEYBOARD', 'MIC WIRELESS', 'INFOCUS', 'KAMERA', 'MICROWAVE', 'MONITOR', 'SPEAKER'],
            'ACCESSORIES' => ['TAS KURIR', 'LAN CONNECTOR', 'LAMPU LED', 'TRIPOD'],
        ];

        foreach ($categories as $parent => $children) {
            $parentCategory = Category::create(['name' => $parent]);

            foreach ($children as $child) {
                Category::create(['name' => $child, 'parent_id' => $parentCategory->id]);
            }
        }
    }
}

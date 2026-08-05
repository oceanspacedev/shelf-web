<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures config/vehicle-asset-reconciliation.php alias targets exist as exact masters.
 * Does not rename or delete existing short-code entities (MAJU, TOP, …) — only inserts missing targets.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('business_entities')) {
            $existing = DB::table('business_entities')->pluck('name')->map(fn ($n) => strtolower((string) $n))->all();

            foreach ($this->businessEntities() as $row) {
                if (in_array(strtolower($row['name']), $existing, true)) {
                    continue;
                }

                DB::table('business_entities')->insert([
                    ...$row,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $existing[] = strtolower($row['name']);
            }
        }

        if (Schema::hasTable('asset_locations')) {
            $existing = DB::table('asset_locations')->pluck('name')->map(fn ($n) => strtolower((string) $n))->all();
            $hasExternal = Schema::hasColumn('asset_locations', 'external_code');

            foreach ($this->locations() as $row) {
                if (in_array(strtolower($row['name']), $existing, true)) {
                    continue;
                }

                $payload = [
                    'name' => $row['name'],
                    'address' => $row['address'],
                    'description' => $row['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($hasExternal) {
                    $payload['external_code'] = $row['external_code'];
                }

                DB::table('asset_locations')->insert($payload);
                $existing[] = strtolower($row['name']);
            }
        }
    }

    public function down(): void
    {
        // Keep masters; they are shared operational data.
    }

    /** @return list<array{name: string, format: string}> */
    private function businessEntities(): array
    {
        return [
            ['name' => 'PT MEDIA SELULAR INDONESIA', 'format' => '120920.MSI/'],
            ['name' => 'CV COMPLETE SELULAR', 'format' => '221218.CS/'],
            ['name' => 'CV TOP SELULAR', 'format' => '191415.TOP/'],
            ['name' => 'PT. MAJU KENDARAAN LISTRIK INDONESIA', 'format' => '1210118.MKLI/'],
            ['name' => 'PT RETAIL INDONESIA SELALU MAJU', 'format' => '1781812.RISM/'],
            ['name' => 'CV BERSAMA CS', 'format' => '221218.BCS/'],
            ['name' => 'CV MAJU TECNOLOGI', 'format' => '120920.MT/'],
        ];
    }

    /** @return list<array{name: string, address: string, description: string, external_code: string}> */
    private function locations(): array
    {
        return [
            [
                'name' => 'HEAD OFFICE PIK',
                'address' => 'PIK, Jakarta',
                'description' => 'Head Office PIK (alias HO/PIK)',
                'external_code' => 'ho',
            ],
            [
                'name' => 'GUDANG PRIMA CENTER',
                'address' => 'Prima Center',
                'description' => 'Gudang Prima Center (alias PC)',
                'external_code' => 'pc',
            ],
            [
                'name' => 'PURWOKERTO',
                'address' => 'Purwokerto',
                'description' => 'Lokasi Purwokerto',
                'external_code' => 'purwokerto',
            ],
            [
                'name' => 'AUTO EV JATIWANGI',
                'address' => 'Jatiwangi',
                'description' => 'Auto EV Jatiwangi (alias JATIWANGI)',
                'external_code' => 'jatiwangi',
            ],
        ];
    }
};

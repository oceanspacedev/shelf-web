<?php

namespace App\Imports;

use App\Models\VehicleChecksheet;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class VehicleChecksheetImport implements ToCollection, WithChunkReading
{
    public function collection(Collection $collection)
    {
        $filteredCollection = $collection->filter(function ($row) {
            return $row[0] !== 'ID INPUT';
        });

        $sortedCollection = $filteredCollection->sortBy(function ($row) {
            preg_match('/\d+$/', $row[0], $matches);

            return $matches[0] ?? 0;
        });

        $recordsToInsert = [];
        $now = now();

        DB::beginTransaction();

        try {
            foreach ($sortedCollection as $row) {
                $departureTime = $this->parseDateTime($row[4]);
                $returnTime = $this->parseDateTime($row[5]);

                $recordsToInsert[] = [
                    'reference_number' => $row[0] ?? null,
                    'start_km' => isset($row[1]) && is_numeric($row[1]) ? $row[1] : null,
                    'end_km' => isset($row[2]) && is_numeric($row[2]) ? $row[2] : null,
                    'pic' => $row[3] ?? null,
                    'departure_time' => $departureTime,
                    'return_time' => $returnTime,
                    'license_plate' => $row[6] ?? null,
                    'departure_photo' => $row[7] ?? null,
                    'return_photo' => $row[8] ?? null,
                    'departure_damage_report' => $row[9] ?? null,
                    'return_damage_report' => $row[10] ?? null,
                    'location' => $row[11] ?? null,
                    'remarks' => $row[12] ?? null,
                    'rental_duration' => isset($row[13]) && is_numeric($row[13]) ? $row[13] : null,
                    'distance_traveled' => isset($row[14]) && is_numeric($row[14]) ? $row[14] : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($recordsToInsert)) {
                foreach (array_chunk($recordsToInsert, 500) as $chunk) {
                    VehicleChecksheet::insert($chunk);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        unset($recordsToInsert, $filteredCollection, $sortedCollection);
        gc_collect_cycles();
    }

    public function chunkSize(): int
    {
        return 500;
    }

    private function parseDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $date = Carbon::instance(Date::excelToDateTimeObject($value));

                return $date->format('Y-m-d H:i:s');
            }

            $cleanedValue = trim($value);
            $cleanedValue = str_replace(['/', '.'], ['-', ':'], $cleanedValue);

            return Carbon::createFromFormat('Y-m-d H:i', $cleanedValue)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}

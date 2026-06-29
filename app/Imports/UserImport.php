<?php

namespace App\Imports;

use App\Models\BusinessEntity;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class UserImport implements ToCollection, WithChunkReading
{
    private array $businessEntityCache = [];

    private array $jobTitleCache = [];

    public function __construct()
    {
        $this->preloadCaches();
    }

    private function preloadCaches(): void
    {
        $this->businessEntityCache = BusinessEntity::pluck('id', 'name')->toArray();
        $this->jobTitleCache = JobTitle::pluck('id', 'title')->toArray();
    }

    public function collection(Collection $rows)
    {
        $usersToInsert = [];
        $now = now();

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                if ($row[0] === 'Nama' || empty($row[0])) {
                    continue;
                }

                if (empty($row[1]) || empty($row[2])) {
                    Log::error('Data untuk Badan Usaha atau Jabatan hilang', [
                        'baris' => $row,
                    ]);

                    continue;
                }

                $businessEntityId = $this->findOrCreateBusinessEntity($row[1]);
                $jobTitleId = $this->findOrCreateJobTitle($row[2]);

                $usersToInsert[] = [
                    'name' => $row[0],
                    'business_entity_id' => $businessEntityId,
                    'job_title_id' => $jobTitleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($usersToInsert)) {
                foreach (array_chunk($usersToInsert, 500) as $chunk) {
                    User::insert($chunk);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Kesalahan saat mengimpor data pengguna', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw ValidationException::withMessages(['import' => 'Terjadi kesalahan saat impor pengguna. Silakan periksa log untuk detail lebih lanjut.']);
        }

        unset($usersToInsert);
        gc_collect_cycles();
    }

    public function chunkSize(): int
    {
        return 500;
    }

    private function findOrCreateBusinessEntity($name): int
    {
        $name = trim($name);

        if (isset($this->businessEntityCache[$name])) {
            return $this->businessEntityCache[$name];
        }

        $entity = BusinessEntity::firstOrCreate(
            ['name' => $name],
            ['created_at' => now(), 'updated_at' => now()]
        );
        $this->businessEntityCache[$name] = $entity->id;

        return $entity->id;
    }

    private function findOrCreateJobTitle($title): int
    {
        $title = trim($title);

        if (isset($this->jobTitleCache[$title])) {
            return $this->jobTitleCache[$title];
        }

        $entity = JobTitle::firstOrCreate(
            ['title' => $title],
            ['created_at' => now(), 'updated_at' => now()]
        );
        $this->jobTitleCache[$title] = $entity->id;

        return $entity->id;
    }
}

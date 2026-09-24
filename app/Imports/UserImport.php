<?php

namespace App\Imports;

use App\Models\BusinessEntity;
use App\Models\JobTitle;
use App\Models\User;
use App\Support\PhoneNumber;
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
        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $name = trim((string) ($row[0] ?? ''));
                if ($name === '' || strcasecmp($name, 'Nama') === 0) {
                    continue;
                }

                $entityName = trim((string) ($row[1] ?? ''));
                $titleName = trim((string) ($row[2] ?? ''));
                if ($entityName === '' || $titleName === '') {
                    Log::error('Data untuk Badan Usaha atau Jabatan hilang', [
                        'baris' => $row,
                    ]);

                    continue;
                }

                $employeeId = trim((string) ($row[3] ?? ''));
                $phoneRaw = trim((string) ($row[4] ?? ''));
                $user = $employeeId !== ''
                    ? User::query()->where('employee_id', $employeeId)->first()
                    : null;

                $payload = [
                    'name' => $name,
                    'business_entity_id' => $this->findOrCreateBusinessEntity($entityName),
                    'job_title_id' => $this->findOrCreateJobTitle($titleName),
                ];

                if ($employeeId !== '') {
                    $payload['employee_id'] = $employeeId;
                }

                if ($phoneRaw !== '') {
                    $contact = preg_replace('/[^\d+]/', '', $phoneRaw) ?: '';
                    $login = PhoneNumber::canonical($phoneRaw);
                    if ($contact === '' || $login === null) {
                        Log::error('Nomor HP tidak valid saat impor pengguna', [
                            'baris' => $row,
                        ]);

                        continue;
                    }

                    $taken = User::query()
                        ->where('whatsapp_login_number', $login)
                        ->when($user, fn ($query) => $query->whereKeyNot($user->getKey()))
                        ->exists();

                    if ($taken) {
                        Log::error('Nomor WhatsApp sudah dipakai user lain', [
                            'baris' => $row,
                        ]);

                        continue;
                    }

                    $payload['whatsapp_number'] = $contact;
                    $payload['whatsapp_login_number'] = $login;
                }

                if ($user) {
                    $user->update($payload);
                } else {
                    User::create($payload);
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

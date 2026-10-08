<?php

namespace App\Imports;

use App\Models\BusinessEntity;
use App\Models\JobTitle;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class UserImport implements ToCollection, WithChunkReading
{
    private array $businessEntityCache = [];

    private array $jobTitleCache = [];

    /**
     * The user running the import. Rows outside their business entities are
     * skipped; null (CLI, queue) means unrestricted.
     */
    private ?User $actor = null;

    /**
     * ExcelImportAction passes ($model, $attributes, $additionalData); none are needed here.
     */
    public function __construct()
    {
        $user = Auth::user();
        $this->actor = $user instanceof User ? $user : null;

        $this->preloadCaches();
    }

    public function forActor(?User $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    private function preloadCaches(): void
    {
        $this->businessEntityCache = BusinessEntity::pluck('id', 'name')->toArray();
        $this->jobTitleCache = JobTitle::pluck('id', 'title')->toArray();
    }

    public function collection(Collection $rows)
    {
        $outsideAccessCount = 0;

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

                $businessEntityId = $this->resolveBusinessEntity($entityName);
                if ($businessEntityId === null) {
                    Log::warning('Badan usaha di luar akses pengguna yang mengimpor, baris dilewati', [
                        'baris' => $row,
                        'actor_id' => $this->actor?->getKey(),
                    ]);
                    $outsideAccessCount++;

                    continue;
                }

                $employeeId = trim((string) ($row[3] ?? ''));
                $phoneRaw = trim((string) ($row[4] ?? ''));
                $user = $employeeId !== ''
                    ? User::query()->where('employee_id', $employeeId)->first()
                    : null;

                if ($user && ! $this->canManage($user)) {
                    Log::warning('User dengan Employee ID ini berada di luar badan usaha yang dapat diakses, baris dilewati', [
                        'baris' => $row,
                        'actor_id' => $this->actor?->getKey(),
                    ]);
                    $outsideAccessCount++;

                    continue;
                }

                $payload = [
                    'name' => $name,
                    'business_entity_id' => $businessEntityId,
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

        // Tanpa ini pengimpor tidak tahu ada baris yang tidak masuk.
        if ($outsideAccessCount > 0 && $this->actor !== null) {
            Notification::make()
                ->title("{$outsideAccessCount} baris user dilewati")
                ->body('Badan usaha atau user pada baris tersebut berada di luar akses badan usaha Anda.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Unrestricted actors may create new business entities on the fly; scoped
     * actors can only import into entities they already have access to.
     */
    private function resolveBusinessEntity(string $name): ?int
    {
        if ($this->actor === null || $this->actor->hasUnrestrictedBusinessEntityAccess()) {
            return $this->findOrCreateBusinessEntity($name);
        }

        $name = trim($name);
        $id = $this->businessEntityCache[$name] ?? BusinessEntity::query()->where('name', $name)->value('id');

        if ($id === null || ! $this->actor->canAccessBusinessEntity($id)) {
            return null;
        }

        return $this->businessEntityCache[$name] = (int) $id;
    }

    private function canManage(User $user): bool
    {
        return $this->actor === null || $this->actor->canAccessBusinessEntity($user->business_entity_id);
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

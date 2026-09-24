<?php

namespace App\Services;

use App\Models\BusinessEntity;
use App\Models\JobTitle;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TalentaUserImportService
{
    /** @var list<string> */
    private const EMPLOYEE_ID_KEYS = ['employee_id', 'id_employee', 'emp_id', 'id_karyawan', 'nip'];

    /** @var list<string> */
    private const EMAIL_KEYS = ['email', 'email_address'];

    /** @var list<string> */
    private const PHONE_KEYS = ['mobile_phone', 'no_hp', 'phone', 'phone_number', 'nomer_hp', 'no_telepon'];

    /** @var list<string> */
    private const BRANCH_KEYS = ['branch', 'business_entity', 'badan_usaha', 'organization'];

    /** @var list<string> */
    private const JOB_KEYS = ['job', 'job_position', 'position', 'position_name', 'jabatan', 'title'];

    /**
     * @return array{success: bool, message: string, success_count: int, error_count: int, errors: list<string>, created_count: int}
     */
    public function importFromFile(string $jsonFilePath): array
    {
        if (! is_file($jsonFilePath) || ! is_readable($jsonFilePath)) {
            return $this->failedResult(
                'File JSON tidak ditemukan atau tidak dapat dibaca di server.',
                ['File JSON tidak ditemukan atau tidak dapat dibaca.'],
            );
        }

        $content = file_get_contents($jsonFilePath);
        if ($content === false) {
            return $this->failedResult('File JSON gagal dibaca.', ['File JSON gagal dibaca.']);
        }

        return $this->importFromContent($content);
    }

    /**
     * @return array{success: bool, message: string, success_count: int, error_count: int, errors: list<string>, created_count: int}
     */
    public function importFromContent(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = trim($content);

        if ($content === '') {
            return $this->failedResult('File JSON kosong (0 byte).', ['File JSON kosong.']);
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $repaired = preg_replace('/,\s*([\]}])/', '$1', $content) ?? $content;
            $decoded = json_decode($repaired, true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = json_last_error_msg();
            Log::error('Talenta user import received invalid JSON', ['error' => $errorMessage]);

            return $this->failedResult(
                'Format JSON tidak valid: '.$errorMessage.'.',
                ['Format JSON tidak valid: '.$errorMessage],
            );
        }

        $rows = $this->extractRows($decoded);
        if ($rows === []) {
            return $this->failedResult(
                'Tidak ada data karyawan ditemukan dalam file JSON.',
                ['Tidak ada data karyawan ditemukan.'],
            );
        }

        $successCount = 0;
        $createdCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            if (! is_array($row)) {
                $errors[] = "Baris #{$rowNumber}: Data karyawan harus berupa object JSON.";

                continue;
            }

            try {
                $created = DB::transaction(fn (): bool => $this->importRow($row));
                if ($created) {
                    $createdCount++;
                }
                $successCount++;
            } catch (Throwable $exception) {
                Log::warning('Talenta user import row rejected', [
                    'row' => $rowNumber,
                    'error' => $exception->getMessage(),
                ]);
                $errors[] = "Baris #{$rowNumber}: {$exception->getMessage()}";
            }
        }

        return [
            'success' => true,
            'message' => "Import Talenta selesai. Berhasil: {$successCount} karyawan.",
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors,
            'created_count' => $createdCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importRow(array $row): bool
    {
        $employeeId = $this->firstScalarValue($row, self::EMPLOYEE_ID_KEYS);
        $fullName = $this->extractFullName($row);
        $existing = $this->resolveExistingUser($employeeId, $fullName);

        $payload = [];

        if ($fullName !== '') {
            $payload['name'] = $fullName;
        }

        if ($employeeId !== '') {
            $payload['employee_id'] = $employeeId;
        }

        if ($this->hasAnyKey($row, self::PHONE_KEYS)) {
            $phone = $this->firstPhone($row);
            if ($phone !== null) {
                $login = PhoneNumber::canonical($phone);
                if ($login === null) {
                    throw new RuntimeException('No. HP tidak valid.');
                }

                $taken = User::query()
                    ->where('whatsapp_login_number', $login)
                    ->when($existing, fn ($query) => $query->whereKeyNot($existing->getKey()))
                    ->exists();

                if ($taken) {
                    throw new RuntimeException('Nomor WhatsApp sudah dipakai user lain.');
                }

                $payload['whatsapp_number'] = $phone;
                $payload['whatsapp_login_number'] = $login;
            }
        }

        if ($this->hasAnyKey($row, self::EMAIL_KEYS)) {
            $email = $this->normalizeEmail($this->firstScalarValue($row, self::EMAIL_KEYS));
            if ($email !== null) {
                $emailTaken = User::query()
                    ->where('email', $email)
                    ->when($existing, fn ($query) => $query->whereKeyNot($existing->getKey()))
                    ->exists();
                $payload['email'] = $emailTaken ? null : $email;
            }
        }

        $branch = $this->firstScalarValue($row, self::BRANCH_KEYS);
        if ($branch !== '') {
            $payload['business_entity_id'] = $this->findOrCreateBusinessEntity($branch);
        }

        $job = $this->firstScalarValue($row, self::JOB_KEYS);
        if ($job !== '') {
            $payload['job_title_id'] = $this->findOrCreateJobTitle($job);
        }

        if ($existing) {
            if ($payload !== []) {
                $existing->update($payload);
            }

            return false;
        }

        if ($employeeId === '') {
            throw new RuntimeException('Employee ID wajib diisi untuk membuat user baru.');
        }
        if ($fullName === '') {
            throw new RuntimeException('Nama wajib diisi untuk membuat user baru.');
        }

        User::create($payload);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractRows(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            foreach (['data', 'employees', 'users', 'items'] as $key) {
                if (isset($decoded['data'][$key]) && is_array($decoded['data'][$key])) {
                    return array_values($decoded['data'][$key]);
                }
            }

            if (array_is_list($decoded['data'])) {
                return $decoded['data'];
            }
            if ($this->isEmployeeRow($decoded['data'])) {
                return [$decoded['data']];
            }
        }

        foreach (['employees', 'users', 'items', 'records', 'results', 'data'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return array_values($decoded[$key]);
            }
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        if ($this->isEmployeeRow($decoded)) {
            return [$decoded];
        }

        $first = reset($decoded);
        if (is_array($first) && $this->isEmployeeRow($first)) {
            return array_values($decoded);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmployeeRow(array $data): bool
    {
        foreach ([
            ...self::EMPLOYEE_ID_KEYS,
            ...self::EMAIL_KEYS,
            ...self::PHONE_KEYS,
            'full_name', 'nama_lengkap', 'name', 'employee_name', 'first_name', 'last_name',
            ...self::JOB_KEYS,
        ] as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    private function resolveExistingUser(string $employeeId, string $fullName): ?User
    {
        if ($employeeId !== '') {
            $matches = User::query()
                ->where('employee_id', $employeeId)
                ->limit(2)
                ->get();

            if ($matches->count() > 1) {
                throw new RuntimeException('Employee ID cocok dengan lebih dari satu user.');
            }

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        if ($fullName === '') {
            return null;
        }

        $canonicalName = $this->canonicalName($fullName);
        $nameMatches = User::query()
            ->where(function ($query): void {
                $query->whereNull('employee_id')
                    ->orWhere('employee_id', '');
            })
            ->get()
            ->filter(fn (User $user): bool => $this->canonicalName((string) $user->name) === $canonicalName)
            ->values();

        if ($nameMatches->count() > 1) {
            throw new RuntimeException('Nama cocok dengan lebih dari satu user tanpa Employee ID.');
        }

        return $nameMatches->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function firstScalarValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            $value = $row[$key];
            if (is_bool($value) || (! is_scalar($value) && ! $value instanceof \Stringable)) {
                throw new RuntimeException("Field {$key} tidak valid.");
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function extractFullName(array $row): string
    {
        $fullName = $this->firstScalarValue($row, [
            'full_name',
            'nama_lengkap',
            'name',
            'employee_name',
        ]);

        if ($fullName !== '') {
            return Str::squish($fullName);
        }

        return Str::squish(
            $this->firstScalarValue($row, ['first_name']).' '.$this->firstScalarValue($row, ['last_name']),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function firstPhone(array $row): ?string
    {
        $phones = [];

        foreach (self::PHONE_KEYS as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            $value = $row[$key];
            if (is_bool($value) || (! is_scalar($value) && ! $value instanceof \Stringable)) {
                throw new RuntimeException('No. HP tidak valid.');
            }

            $cleaned = preg_replace('/[^\d+]/', '', trim((string) $value)) ?: '';
            if ($cleaned === '') {
                continue;
            }

            $canonical = PhoneNumber::canonical($cleaned);
            if ($canonical === null) {
                throw new RuntimeException('No. HP tidak valid.');
            }

            $phones[$canonical] = $cleaned;
        }

        if ($phones === []) {
            return null;
        }

        if (count($phones) > 1) {
            throw new RuntimeException('No. HP memiliki beberapa nilai yang berbeda pada field alias.');
        }

        return array_values($phones)[0];
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = Str::lower(trim($email));
        if ($email === '') {
            return null;
        }

        if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Format email tidak valid.');
        }

        return $email;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function hasAnyKey(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return true;
            }
        }

        return false;
    }

    private function canonicalName(string $name): string
    {
        return Str::lower(Str::squish($name));
    }

    private function findOrCreateBusinessEntity(string $name): int
    {
        return BusinessEntity::firstOrCreate(
            ['name' => trim($name)],
            ['created_at' => now(), 'updated_at' => now()],
        )->id;
    }

    private function findOrCreateJobTitle(string $title): int
    {
        $title = trim($title);
        $existing = JobTitle::query()->where('title', $title)->first();
        if ($existing) {
            return $existing->id;
        }

        return JobTitle::create(['title' => $title])->id;
    }

    /**
     * @param  list<string>  $errors
     * @return array{success: bool, message: string, success_count: int, error_count: int, errors: list<string>, created_count: int}
     */
    private function failedResult(string $message, array $errors): array
    {
        return [
            'success' => false,
            'message' => $message,
            'success_count' => 0,
            'error_count' => count($errors),
            'errors' => $errors,
            'created_count' => 0,
        ];
    }
}

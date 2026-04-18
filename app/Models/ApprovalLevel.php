<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ApprovalLevel extends Model
{
    use HasFactory;

    public const ALL_DIVISIONS = '*';

    protected $fillable = [
        'request_type',
        'division',
        'level',
        'approver_name',
        'approver_email',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $approvalLevel): void {
            $approvalLevel->division = self::normalizeDivision($approvalLevel->division);
            $approvalLevel->ensureUniqueTrack();
        });
    }

    public static function normalizeDivision(?string $division): string
    {
        $division = preg_replace('/\s+/u', ' ', trim((string) $division)) ?? '';

        return $division === '' ? self::ALL_DIVISIONS : $division;
    }

    public static function normalizeDivisionKey(?string $division): string
    {
        return mb_strtolower(self::normalizeDivision($division));
    }

    public function scopeForTrack(Builder $query, string $requestType, ?string $division): Builder
    {
        return $query
            ->where('request_type', $requestType)
            ->whereRaw('LOWER(division) = ?', [self::normalizeDivisionKey($division)]);
    }

    public function usesAllDivisions(): bool
    {
        return $this->division === self::ALL_DIVISIONS;
    }

    public function requestApprovals(): HasMany
    {
        return $this->hasMany(RequestApproval::class);
    }

    private function ensureUniqueTrack(): void
    {
        if (! $this->request_type || ! $this->level) {
            return;
        }

        $duplicateExists = self::query()
            ->where('request_type', $this->request_type)
            ->where('level', $this->level)
            ->whereRaw('LOWER(division) = ?', [self::normalizeDivisionKey($this->division)])
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();

        if (! $duplicateExists) {
            return;
        }

        $divisionLabel = $this->usesAllDivisions() ? 'Semua Divisi' : $this->division;

        throw ValidationException::withMessages([
            'level' => "Konfigurasi approval untuk divisi {$divisionLabel} di level {$this->level} sudah ada.",
        ]);
    }
}

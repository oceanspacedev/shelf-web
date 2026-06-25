<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AssetAttribute extends Model
{
    use HasFactory;

    public const STATUS_SAFE = 'aman';

    public const STATUS_DUE_SOON = 'perlu_diperbarui';

    public const STATUS_DUE_TODAY = 'jatuh_tempo_hari_ini';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_NO_EXPIRY = 'tanpa_tanggal';

    protected $fillable = [
        'asset_id',
        'custom_attribute_id',
        'attribute_value',
    ];

    // Relasi ke aset
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    // Relasi ke custom attribute
    public function customAttribute()
    {
        return $this->belongsTo(CustomAssetAttribute::class, 'custom_attribute_id');
    }

    public static function documentValue(array $value): string
    {
        return json_encode([
            'expires_at' => $value['expires_at'] ?? null,
            'document_number' => $value['document_number'] ?? null,
            'document_path' => $value['document_path'] ?? null,
            'notes' => $value['notes'] ?? null,
            'renewed_at' => $value['renewed_at'] ?? now()->toDateString(),
        ], JSON_UNESCAPED_SLASHES);
    }

    public function isDocumentExpiryAttribute(): bool
    {
        return $this->customAttribute?->type === CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY;
    }

    public function documentPayload(): array
    {
        $value = $this->attribute_value;

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function expiryDate(): ?CarbonImmutable
    {
        $value = $this->isDocumentExpiryAttribute()
            ? ($this->documentPayload()['expires_at'] ?? null)
            : $this->attribute_value;

        if (! filled($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Exception) {
            return null;
        }
    }

    public function documentPath(): ?string
    {
        $path = $this->documentPayload()['document_path'] ?? null;

        return filled($path) ? $path : null;
    }

    public function documentUrl(): ?string
    {
        $path = $this->documentPath();

        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return Storage::url($path);
    }

    public function documentNumber(): ?string
    {
        $number = $this->documentPayload()['document_number'] ?? null;

        return filled($number) ? $number : null;
    }

    public function documentNotes(): ?string
    {
        $notes = $this->documentPayload()['notes'] ?? null;

        return filled($notes) ? $notes : null;
    }

    public function reminderStartDays(): int
    {
        $payload = $this->documentPayload();
        $days = $payload['reminder_start_days']
            ?? $this->customAttribute?->notification_offset
            ?? 0;

        return max(0, (int) $days);
    }

    public function shouldSendExpiryReminderOn(null|string|CarbonInterface $date = null): bool
    {
        $customAttribute = $this->customAttribute;

        if (! $customAttribute?->is_notifiable || $customAttribute->notification_type !== 'relative_date') {
            return false;
        }

        $expiryDate = $this->expiryDate();

        if (! $expiryDate) {
            return false;
        }

        $today = $this->normalizeDate($date);
        $reminderStartDate = $expiryDate->subDays($this->reminderStartDays());

        return $today->greaterThanOrEqualTo($reminderStartDate);
    }

    public function expiryReminderStatusOn(null|string|CarbonInterface $date = null): string
    {
        $expiryDate = $this->expiryDate();

        if (! $expiryDate) {
            return self::STATUS_NO_EXPIRY;
        }

        $today = $this->normalizeDate($date);

        if ($today->greaterThan($expiryDate)) {
            return self::STATUS_EXPIRED;
        }

        if ($today->isSameDay($expiryDate)) {
            return self::STATUS_DUE_TODAY;
        }

        $daysUntilExpiry = (int) $today->diffInDays($expiryDate);

        if ($daysUntilExpiry <= $this->reminderStartDays()) {
            return self::STATUS_DUE_SOON;
        }

        return self::STATUS_SAFE;
    }

    public function expiryReminderStatusLabelOn(null|string|CarbonInterface $date = null): string
    {
        return match ($this->expiryReminderStatusOn($date)) {
            self::STATUS_DUE_SOON => 'Perlu diperbarui',
            self::STATUS_DUE_TODAY => 'Jatuh tempo hari ini',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_NO_EXPIRY => 'Tanggal belum diisi',
            default => 'Aman',
        };
    }

    public function expiryReminderStatusColorOn(null|string|CarbonInterface $date = null): string
    {
        return match ($this->expiryReminderStatusOn($date)) {
            self::STATUS_DUE_SOON => 'warning',
            self::STATUS_DUE_TODAY, self::STATUS_EXPIRED => 'danger',
            self::STATUS_NO_EXPIRY => 'gray',
            default => 'success',
        };
    }

    public function displayValue(): string
    {
        if (! $this->isDocumentExpiryAttribute()) {
            return (string) ($this->attribute_value ?? '-');
        }

        $parts = [];

        if ($this->documentNumber()) {
            $parts[] = 'No. '.$this->documentNumber();
        }

        $parts[] = 'Berlaku sampai '.($this->expiryDate()?->format('d M Y') ?? '-');
        $parts[] = $this->expiryReminderStatusLabelOn();

        return implode(' | ', $parts);
    }

    protected function normalizeDate(null|string|CarbonInterface $date = null): CarbonImmutable
    {
        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::parse($date->toDateString())->startOfDay();
        }

        return CarbonImmutable::parse($date ?? now())->startOfDay();
    }
}

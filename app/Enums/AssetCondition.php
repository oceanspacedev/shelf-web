<?php

namespace App\Enums;

enum AssetCondition: string
{
    case Available = 'available';
    case Transferred = 'transferred';
    case Sold = 'sold';
    case Lost = 'lost';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Transferred => 'Digunakan',
            self::Sold => 'Dijual',
            self::Lost => 'Hilang',
            self::Damaged => 'Rusak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Transferred => 'warning',
            self::Sold => 'gray',
            self::Lost => 'danger',
            self::Damaged => 'danger',
        };
    }

    public function isIncident(): bool
    {
        return in_array($this, [self::Lost, self::Damaged], true);
    }

    public function isTransferable(): bool
    {
        return in_array($this, [self::Available, self::Transferred], true);
    }

    public static function incidentValues(): array
    {
        return [
            self::Lost->value,
            self::Damaged->value,
        ];
    }

    public static function transferableValues(): array
    {
        return [
            self::Available->value,
            self::Transferred->value,
        ];
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}

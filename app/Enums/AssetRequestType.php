<?php

namespace App\Enums;

enum AssetRequestType: string
{
    case Pengadaan = 'pengadaan';
    case Penarikan = 'penarikan';
    case Perbaikan = 'perbaikan';

    public function label(): string
    {
        return match ($this) {
            self::Pengadaan => 'Pengadaan',
            self::Penarikan => 'Penarikan',
            self::Perbaikan => 'Perbaikan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pengadaan => 'success',
            self::Penarikan => 'warning',
            self::Perbaikan => 'info',
        };
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

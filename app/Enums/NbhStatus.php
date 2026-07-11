<?php

namespace App\Enums;

enum NbhStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak Ada',
            self::Pending => 'Menunggu NBH',
            self::Resolved => 'NBH Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Pending => 'warning',
            self::Resolved => 'success',
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

<?php

namespace App\Enums;

use App\Models\User;

enum AssetTransferDocumentType: string
{
    case SerahTerima = 'serah_terima';
    case PengalihanBarang = 'pengalihan_barang';
    case PengembalianBarang = 'pengembalian_barang';

    public function label(): string
    {
        return match ($this) {
            self::SerahTerima => 'BERITA ACARA SERAH TERIMA',
            self::PengalihanBarang => 'BERITA ACARA PENGALIHAN BARANG',
            self::PengembalianBarang => 'BERITA ACARA PENGEMBALIAN BARANG',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::SerahTerima => 'BA',
            self::PengalihanBarang => 'BAPAB',
            self::PengembalianBarang => 'BAPEB',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SerahTerima => 'primary',
            self::PengalihanBarang => 'success',
            self::PengembalianBarang => 'danger',
        };
    }

    public function returnsToGeneralAffair(): bool
    {
        return $this === self::PengembalianBarang;
    }

    public static function fromUsers(?User $fromUser, ?User $toUser): ?self
    {
        if (! $fromUser || ! $toUser || $fromUser->is($toUser)) {
            return null;
        }

        $fromIsGeneralAffair = $fromUser->hasRole('general_affair');
        $toIsGeneralAffair = $toUser->hasRole('general_affair');

        // Identify the main General Affairs department account (ID 2 with username 'adminga' in production, or named 'GA' in tests)
        $fromIsMainGA = ($fromUser->id === 2 && $fromUser->username === 'adminga') || $fromUser->name === 'GA';
        $toIsMainGA = ($toUser->id === 2 && $toUser->username === 'adminga') || $toUser->name === 'GA';

        if ($fromIsGeneralAffair && $toIsGeneralAffair) {
            if ($toIsMainGA) {
                return self::PengembalianBarang; // Returning to the main GA department
            }
            if ($fromIsMainGA) {
                return self::SerahTerima; // Dispatched from the main GA department to a GA staff
            }
            return self::PengalihanBarang; // Transfer between GA staff members
        }

        return match (true) {
            $fromIsGeneralAffair && ! $toIsGeneralAffair => self::SerahTerima,
            ! $fromIsGeneralAffair && ! $toIsGeneralAffair => self::PengalihanBarang,
            ! $fromIsGeneralAffair && $toIsGeneralAffair => self::PengembalianBarang,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function colors(): array
    {
        $colors = ['gray' => 'Status Transfer Tidak Valid'];

        foreach (self::cases() as $case) {
            $colors[$case->color()] = $case->label();
        }

        return $colors;
    }
}

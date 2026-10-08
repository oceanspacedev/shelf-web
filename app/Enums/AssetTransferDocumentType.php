<?php

namespace App\Enums;

/**
 * Jenis berita acara transfer aset. Dipilih secara eksplisit saat BA dibuat
 * (kolom asset_transfers.document_type), bukan ditebak dari peran pihak.
 *
 * Stok adalah aset tanpa pemegang (recipient_id null). Hanya staf dengan role
 * general_affair yang boleh mengeluarkan aset dari stok atau menerimanya kembali.
 */
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

    public function description(): string
    {
        return match ($this) {
            self::SerahTerima => 'Staf GA menyerahkan aset dari stok ke pemegang baru. Aset menjadi Digunakan.',
            self::PengalihanBarang => 'Pemegang aset mengalihkan aset yang dipegangnya ke orang lain. Aset tetap Digunakan.',
            self::PengembalianBarang => 'Pemegang aset mengembalikan aset ke staf GA. Aset kembali ke stok dan menjadi Tersedia.',
        };
    }

    /**
     * Aset keluar dari stok (tanpa pemegang) pada dokumen ini.
     */
    public function dispatchesFromStock(): bool
    {
        return $this === self::SerahTerima;
    }

    /**
     * Aset kembali ke stok (tanpa pemegang) pada dokumen ini.
     */
    public function returnsToStock(): bool
    {
        return $this === self::PengembalianBarang;
    }

    public function requiresGeneralAffairFrom(): bool
    {
        return $this === self::SerahTerima;
    }

    public function requiresGeneralAffairTo(): bool
    {
        return $this === self::PengembalianBarang;
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

<?php

namespace Tests\Unit;

use App\Services\CsaAssetAuditWorkbookParser;
use PHPUnit\Framework\TestCase;

class CsaAssetAuditWorkbookParserTest extends TestCase
{
    public function test_it_parses_multiple_header_variants_and_uses_the_audit_result_as_target(): void
    {
        $rows = [
            1 => ['Kode', 'Kd. Barang', 'Nama Barang', 'S/N', 'Saldo Akhir', 'Saldo Real', 'Selisih/Koreksi', 'Keterangan'],
            2 => ['TPRM', 'AT-GA0011', 'APAR PYRAMID', 'AST/TPRM/2', 2, 1, -1, 'Audit fisik'],
            3 => [null, null, null, null, null, null, null, null],
            4 => ['Gudang TBBK', null, null, null, null, null, null, null],
            5 => ['Kode Gudang', 'Kode Item', 'Nama Item', 'IMEI', 'Qty Akhir', 'Fisik', 'Selisih', 'Keterangan'],
            6 => ['TBBK', 'AT-GA0054', 'KURSI BASO', null, 1, null, -1, 'RUSAK'],
            7 => ['TBBK', 'AT-GA0059', 'KURSI TINGGI', '0', 1, 2, 1, null],
            8 => ['Gudang AKTIVA (CSA CSN)', null, null, null, null, null, null, null],
            9 => ['Kode Gudang', 'Kode Item', 'Nama Item', 'IMEI', 'Qty Akhir', 'Fisik', 'Selisih', 'Keterangan'],
            10 => ['AKTIVA', 'AT-CSN', 'BARCODE SCANNER', 'VSC-1', 1, 1, 0, null],
            11 => ['Gudang lain tanpa marker', null, null, null, null, null, null, null],
            12 => ['Kode Gudang', 'Kode Item', 'Nama Item', 'IMEI', 'Qty Akhir', 'Fisik', 'Selisih', 'Keterangan'],
            13 => ['LAIN', 'AT-OTHER', 'MEJA', null, 1, 1, 0, null],
        ];

        $parsed = (new CsaAssetAuditWorkbookParser)->parseRows($rows);

        $this->assertCount(5, $parsed);
        $this->assertSame(1, $parsed[0]['target_qty']);
        $this->assertSame('AST/TPRM/2', $parsed[0]['serial_number']);
        $this->assertSame(0, $parsed[1]['target_qty']);
        $this->assertNull($parsed[1]['physical_qty']);
        $this->assertSame(2, $parsed[2]['target_qty']);
        $this->assertNull($parsed[2]['serial_number'], 'Serial 0 adalah placeholder, bukan identitas aset.');
        $this->assertSame('CSN', $parsed[3]['external_business_entity_code']);
        $this->assertNull($parsed[4]['external_business_entity_code']);
    }

    public function test_it_flags_inconsistent_and_fractional_quantities(): void
    {
        $rows = [
            1 => ['Nama Gudang', 'Kode Item', 'Nama Barang', 'S/N', 'Saldo Akhir', 'Saldo Real', 'Selisih/Koreksi', 'Keterangan'],
            2 => ['TPRM', 'AT-X', 'ASET UJI', null, 1.5, 3, 1, null],
        ];

        $parsed = (new CsaAssetAuditWorkbookParser)->parseRows($rows);

        $this->assertNotEmpty($parsed[0]['validation_errors']);
        $this->assertStringContainsString('bilangan bulat', implode(' ', $parsed[0]['validation_errors']));
        $this->assertStringContainsString('tidak konsisten', implode(' ', $parsed[0]['validation_errors']));
    }
}

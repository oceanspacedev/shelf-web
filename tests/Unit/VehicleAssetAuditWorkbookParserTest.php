<?php

namespace Tests\Unit;

use App\Services\VehicleAssetAuditWorkbookParser;
use App\Support\VehiclePlateNormalizer;
use PHPUnit\Framework\TestCase;

class VehicleAssetAuditWorkbookParserTest extends TestCase
{
    public function test_it_normalizes_plates_and_marks_sold_rows(): void
    {
        $rows = [
            2 => ['NO', 'NOMOR POLISI', 'NAMA STNK', 'ACCOUNTING', 'CEK KEBERADAAN UNIT', 'MERK KENDARAAN', 'NAMA KENDARAAN', 'JENIS KENDARAAN', 'NO MESIN', 'PEMEGANG INVENTARIS', 'STATUS BPKB'],
            3 => [1, 'H9527EA', 'PT MKLI / TERJUAL', 'TERJUAL', 'HO', 'SUZUKI', 'CARRY', 'PICK UP', 'MHYHDC61TNJ249848 / K15B', 'HO', 'LEASING'],
            4 => [2, 'E 1239 DV', 'PT MKLI', 'MSI', 'BU RIKA', 'DAIHATSU', 'SIGRA', 'MINIBUS', 'MHKS6GK6JNJ026345 / 3NRH', 'RIKA', 'ADA'],
        ];

        $parsed = (new VehicleAssetAuditWorkbookParser)->parseRows($rows);

        $this->assertCount(2, $parsed);
        $this->assertSame('H 9527 EA', $parsed[0]['external_item_code']);
        $this->assertSame(0, $parsed[0]['target_qty']);
        $this->assertSame('sold', $parsed[0]['raw_payload']['disposition']);
        $this->assertSame('MHYHDC61TNJ249848', $parsed[0]['serial_number']);
        $this->assertSame(1, $parsed[1]['target_qty']);
        $this->assertSame('MSI', $parsed[1]['external_business_entity_code']);
        $this->assertSame('e1239dv', VehiclePlateNormalizer::key($parsed[1]['external_item_code']));
    }

    public function test_it_parses_fixture_workbook(): void
    {
        $path = dirname(__DIR__).'/fixtures/vehicle-audit-sample.xlsx';
        $parsed = (new VehicleAssetAuditWorkbookParser)->parse($path);

        $this->assertCount(5, $parsed);
        $sold = collect($parsed)->firstWhere('external_item_code', 'H 9527 EA');
        $this->assertSame(0, $sold['target_qty']);
        $missing = collect($parsed)->firstWhere('external_item_code', 'E 9999 ZZ');
        $this->assertSame('TOP', $missing['external_business_entity_code']);
        $this->assertSame('2026-11-01', $missing['raw_payload']['stnk_expires_at']);
        $this->assertSame('2026-09-15', $missing['raw_payload']['kir_expires_at']);
        $this->assertSame('2026-10-01', $missing['raw_payload']['tax_expires_at']);
        $this->assertSame('2026-05-01', $missing['raw_payload']['insurance_expires_at']);
        $this->assertSame('POL5', $missing['raw_payload']['insurance_policy_number']);
        $this->assertSame('TEST HOLDER', $missing['raw_payload']['holder']);
        $this->assertSame('N-9999', $missing['raw_payload']['bpkb_number']);

        $carry = collect($parsed)->firstWhere('external_item_code', 'H 9526 EA');
        $this->assertSame('2027-02-07', $carry['raw_payload']['stnk_expires_at']);
        $this->assertSame('2026-10-02', $carry['raw_payload']['kir_expires_at']);
        $this->assertSame('JATIWANGI', $carry['raw_payload']['presence']);
    }

    public function test_it_merges_kir_validity_by_plate(): void
    {
        $parser = new VehicleAssetAuditWorkbookParser;
        $rows = [
            2 => ['NO', 'NOMOR POLISI', 'NAMA KENDARAAN', 'ACCOUNTING', 'EXPIRED STNK H-45'],
            3 => [1, 'E 8195 BY', 'GRANMAX', 'MSI', 46425],
        ];
        $parsed = $parser->parseRows($rows);
        $merged = $parser->mergeKirDates($parsed, [
            'e8195by' => '2026-11-12',
        ]);

        $this->assertSame('2027-02-07', $merged[0]['raw_payload']['stnk_expires_at']);
        $this->assertSame('2026-11-12', $merged[0]['raw_payload']['kir_expires_at']);
    }
}

<?php

namespace Tests\Unit;

use App\Enums\AssetCondition;
use App\Imports\AssetImport;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class AssetImportTest extends TestCase
{
    public function test_sold_import_does_not_require_sale_document_path(): void
    {
        $import = (new ReflectionClass(AssetImport::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($import, 'buildSaleAuditPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($import, [
            'nama_aset' => 'Laptop Audit',
            'tanggal_jual' => '2026-04-18',
            'dijual_ke' => 'PT Pembeli',
            'harga_jual' => '0',
            'dokumen_penjualan' => '',
            'catatan_penjualan' => 'Tanpa lampiran saat import awal',
        ], AssetCondition::Sold->value, 0);

        $this->assertSame('2026-04-18', $payload['sold_at']);
        $this->assertSame('PT Pembeli', $payload['sold_to']);
        $this->assertSame(0, $payload['sold_price']);
        $this->assertNull($payload['sale_document_path']);
    }
}

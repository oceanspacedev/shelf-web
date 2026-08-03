# Test Execution

Status: Reviewed

Evidence date: 2026-08-03 (export + polish); prior real-data compare 2026-08-01.

- Export CSA: focused `php artisan test tests/Feature/CsaAssetAuditWorkbookExporterTest.php tests/Unit/CsaAssetAuditWorkbookParserTest.php tests/Feature/AssetReconciliationServiceTest.php` — 15 passed.
- Workbook nyata: parser membaca 650 baris, 27 gudang, 64 target nol, dan 0 baris kuantitas invalid.
- Agregasi workbook nyata: 650 baris sumber menjadi 629 saldo logis; 21 pengulangan tanpa serial tergabung dan 0 identitas duplikat tersisa.
- Command focused: `php artisan test tests/Feature/AssetReconciliationServiceTest.php tests/Unit/CsaAssetAuditWorkbookParserTest.php tests/Feature/FilamentShieldIntegrationTest.php`.
- Result focused: 25 tests passed, 370 assertions. Termasuk marker CSN exact, default Retail, override Gudang, unknown alias Blocked, target per item, dan penolakan staging lama tanpa mapping.
- Migrasi `2026_08_01_000002_add_business_entity_to_asset_reconciliations` berhasil diterapkan tanpa backfill atau perubahan Asset otomatis.
- Migrasi `2026_08_01_000003_add_per_item_business_entity_to_asset_reconciliations` berhasil diterapkan tanpa backfill atau perubahan Asset otomatis.
- Preview batch #2 yang menganggap seluruh workbook Retail dinyatakan superseded dan tidak boleh di-apply.
- Preview nyata batch #3: 629 saldo logis; 496 Retail dan 133 CSN; 58 Inline, 536 Gap, 35 Blocked, 0 item tanpa mapping. Marker CSN terdapat pada Gudang AKTCOM, AKTIVA, AKTUBX, dan COMCLD. Tidak ada Asset diubah oleh preview.
- Seluruh 35 Blocked berasal dari lebih dari satu kandidat Asset berserial untuk baris agregat; sistem tidak memilih kandidat secara otomatis.
- Full suite: 156 passed, 3 failed, 957 assertions. Tiga failure tetap berada di area lama yang tidak disentuh fitur ini: ukuran action `AssetRequest`, scope lifecycle `AssetTransfer`, dan fixture tabel approval pada `PdfAuthorizationTest`.

Validasi browser interaktif masih perlu dilakukan dengan user berizin import sebelum 536 Gap diterapkan. Batch #3 tidak dapat di-apply sebelum 35 baris Blocked diselesaikan.

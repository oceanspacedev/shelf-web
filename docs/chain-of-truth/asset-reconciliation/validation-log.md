# Validation Log

Status: Reviewed

| Date | Artifact / Gate | Evidence | Result |
|---|---|---|---|
| 2026-08-01 | Workbook baseline | Render dua sheet dan ekstraksi sheet ASET | Reviewed |
| 2026-08-01 | Requirements and flows | Stakeholder request + observed Shelf schema | Reviewed, stakeholder validation pending |
| 2026-08-01 | Data model and APIs | Migration, Eloquent models, service contracts | Reviewed |
| 2026-08-01 | FR-015 initial business entity | Stakeholder awal menyatakan Retail | Superseded oleh klarifikasi bahwa workbook juga memuat CSN |
| 2026-08-01 | FR-015..017 mixed entities | Stakeholder menyatakan sebagian data adalah CSN/Complete Solusi Nusantara; workbook menunjukkan marker `(CSA CSN)` | Validated untuk aturan mixed Retail + CSN dan no-guess |
| 2026-08-01 | Focused tests | 25 tests, 370 assertions | Passed |
| 2026-08-01 | Real-data compare | Batch #3, per-item Retail + CSN mapping | 496 Retail, 133 CSN, 58 Inline, 536 Gap, 35 Blocked; no Asset mutation |
| 2026-08-01 | Full regression | 156 passed, 3 unrelated failures, 957 assertions | Feature slice passed; residual repo failures recorded |
| 2026-08-03 | Sync pipeline principle | Stakeholder: sinkronisasi ke depan harus tarik data lewat Import dan Laporan dulu agar flow rapi | Validated untuk FR-018 / NFR-006 (Import → Laporan → Apply) |
| 2026-08-03 | CSA export template | Stakeholder: perlu Export format CSA agar user isi sesuai format lalu Import lebih mudah | Reviewed untuk FR-019 / FR-020; pipeline menjadi Export → Import → Laporan → Apply |
| 2026-08-03 | TC-016 export round-trip | `php artisan test tests/Feature/CsaAssetAuditWorkbookExporterTest.php` (+ parser/service suite, 15 passed) | Passed |
| 2026-08-03 | UX polish pipeline | Label tahap, subheading, Apply disabled saat Blocked, sheet PETUNJUK, Export di Assets + Create, failed status pada create error | Reviewed |

Pengecualian gate: implementasi dilakukan sebelum status Validated karena permintaan stakeholder secara eksplisit mencakup pembangunan fitur. Penggunaan produksi pertama tetap memerlukan approval hasil preview. Prinsip pipeline sync eksternal sudah dikunci.

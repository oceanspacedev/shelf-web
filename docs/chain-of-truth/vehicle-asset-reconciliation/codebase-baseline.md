# Baseline Rekonsiliasi Audit Kendaraan

Status: Reviewed (2026-08-03)

Metode: Chain of Truth dari Farid Suryanto dan Muhammad Ibnu Athoillah.

## Evidence ledger

- Observed — Workbook `18-07-2026 LHP AUDIT ASURANSI DAN ASET KENDARAAN.xlsx` berisi sheet `Monitoring Asset` (daftar unit), `LHP` (temuan), `SHELF` (ekspor aset), serta sumber ACC/BPKB/KIR.
- Observed — Shelf menyimpan kendaraan sebagai `Asset` kategori `MOBIL`/`MOTOR` dengan plat di custom attribute `Plat Nomor`.
- Observed — Rekonsiliasi CSA (`source_system=CSA`) hanya mengatur qty/lokasi/katalog dan tidak menulis plat atau `condition_status=sold`.
- Observed — LHP 18 Juli 2026 menandai kendaraan terjual masih di Shelf, double input plat, gap GA↔Accounting, dan BAST pemegang kosong.
- Observed — DB lokal memiliki 34 aset MOBIL/MOTOR; beberapa plat double (H 9526 EA, H 9913/9914 EA, E 1239 DV).
- Observed — Alias ACC (`MSI`, `CS`, `TOP`, `MKLI`, …) dan keberadaan (`HO`, `PC`, `JATIWANGI`, …) dipetakan exact di `config/vehicle-asset-reconciliation.php`; seeder lokal memakai nama target yang sama; `php artisan vehicle-audit:validate-masters` memverifikasi resolusi master.
- Inferred — Sheet `Monitoring Asset` adalah sumber baris unit untuk compare; sheet `LHP` pelengkap rekomendasi.
- Conflict — LHP menyebut triple pada E 1297 DV; data SHELF/Monitoring menunjukkan triple pada E 1239 DV. Implementasi memakai plat aktual di workbook.

## Pola sinkronisasi

```text
Export format Monitoring Asset (Shelf) → isi audit → Import (stage) → Laporan → Apply
```

Jalur CSA stok tetap terpisah. Mutasi langsung dari Excel ke `assets` dilarang.

## Batas perubahan

Menambah parser/export/service kendaraan, cabang `source_system=VEHICLE_AUDIT` pada batch rekonsiliasi existing, UI create/view bercabang, dan tes. Tidak hard-delete aset. Tidak menyelesaikan gap Accounting↔GA di luar Shelf.

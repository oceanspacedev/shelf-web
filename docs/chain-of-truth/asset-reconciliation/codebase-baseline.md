# Baseline Rekonsiliasi Aset Shelf

Status: Reviewed (2026-08-03)

Metode: Chain of Truth dari Farid Suryanto dan Muhammad Ibnu Athoillah. Referensi: https://faridsurya-dev.github.io/Vibe-Coding-Research/welcome dan https://doi.org/10.5281/zenodo.20767965.

## Evidence ledger

- Observed — Shelf menggunakan Laravel 12, Filament 4, Eloquent `Asset`, dan `AssetLocation`.
- Observed — `Asset` sudah menyimpan `qty`, lokasi, nama, serial, IMEI, kondisi, dan riwayat operasional. Import lama langsung menambah baris aset tanpa preview rekonsiliasi.
- Observed — Stakeholder (2026-08-03) menetapkan bahwa setiap sinkronisasi dari sistem lain ke depan wajib menarik data lewat pola Import lalu Laporan sebelum Apply, agar flow tetap rapi dan tidak ada mutasi langsung.
- Observed — `condition_status` dan `is_available` merepresentasikan kondisi/pemegang aset. Keduanya tidak aman dipakai sebagai penanda saldo audit nol.
- Observed — Workbook `Laporan Asset Toko Ritel 2026 (1).xlsx`, sheet `ASET`, berisi 650 baris sumber pada 27 gudang. Ada 198 baris dengan serial, 90 baris berkoreksi, dan 64 baris bertarget saldo nol.
- Observed — Header workbook berubah antarblok (`Kode`, `Kode Gudang`, `Nama Gudang`; `Kd. Barang`, `Kode Item`; `Saldo Akhir`, `Qty Akhir`; `Saldo Real`, `Fisik`).
- Observed — 21 baris berulang tanpa serial adalah unit-unit dari item yang sama. Setelah agregasi Gudang + Kode Item/Nama, terdapat 629 saldo logis dan tidak ada identitas duplikat tersisa.
- Conflict — Workbook menyebut entitas yang sama sebagai Barang dan Item, sedangkan Shelf hanya memiliki Asset. Implementasi memisahkan master identitas eksternal (`AssetCatalogItem`) dari saldo aset per lokasi (`Asset`).
- Inferred — Sheet `ASET` adalah sumber audit yang dimaksud untuk koreksi; sheet `TUGAS KERJAAKAN` adalah ringkasan stok operasional dan tidak menjadi input fitur ini.
- Unknown — Tidak semua kode gudang CSA sudah memiliki lokasi Shelf dengan nama/kode yang sama. Fitur karena itu mendukung kode eksternal lokasi dan pembuatan lokasi saat apply.

## Pola sinkronisasi yang berlaku

```text
Export format CSA (saldo Shelf) → isi audit → Import (stage) → Laporan (Inline/Gap/Blocked) → Apply
```

Import template Shelf reguler tetap jalur terpisah. Jalur rekonsiliasi eksternal (CSA dan sumber serupa ke depan) tidak boleh menulis `Asset` sebelum laporan ditinjau.

## Batas perubahan

Perubahan menambahkan parser audit, staging compare, katalog Barang/Item eksternal, penerapan koreksi atomik, UI Filament, dan test. Import aset lama dipertahankan untuk template Shelf reguler. Prinsip Import → Laporan → Apply dikunci sebagai pola wajib sinkronisasi eksternal.

## Gate

Dokumen ini Reviewed. Prinsip pipeline sinkronisasi eksternal dikunci dari pernyataan stakeholder 2026-08-03; validasi bisnis preview produksi pertama tetap diperlukan sebelum apply massal.

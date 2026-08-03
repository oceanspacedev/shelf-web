# Information Architecture

Status: Reviewed

Pipeline navigasi sinkronisasi eksternal: Export → Import → Laporan → Apply.

- PAGE-001 — Daftar Asset: aksi `0. Export Format CSA` dan `Import & Laporan Audit CSA`; Import Excel reguler tetap terpisah.
- PAGE-002 — Daftar Rekonsiliasi Audit: subheading pipeline, aksi `0. Export Format CSA`, daftar batch, dan `1. Import Audit CSA`; empty state mengarahkan ke alur yang sama.
- PAGE-003 — Import Audit: workbook (hasil export yang sudah diisi), nama sheet, badan usaha default, opsi pembuatan lokasi; header tetap punya Export; setelah submit otomatis menghasilkan Laporan.
- PAGE-004 — Detail Laporan: subheading kontekstual (Blocked/Gap/Applied), ringkasan, tabel item (Blocked/Gap di atas), Apply disabled saat Blocked, dan `Compare Ulang`.
- PAGE-005 — Master Lokasi: field `Kode Gudang CSA` untuk mapping eksplisit.

Navigasi berada pada grup Asset. Import reguler Shelf dan jalur rekonsiliasi eksternal dipisah secara eksplisit; sync eksternal tidak punya pintasan Apply tanpa melewati Import dan Laporan.

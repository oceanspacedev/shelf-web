# UC-000 — Export Format CSA

Status: Reviewed

Trigger: pengguna memilih `0. Export Format CSA`.

Precondition: pengguna memiliki izin `import_asset` atau `export` pada Asset; terdapat aset inventori aktif dengan lokasi.

Main path:

1. Sistem mengambil aset inventori aktif beserta lokasi, katalog, dan badan usaha.
2. Sistem mengelompokkan baris per badan usaha lalu per gudang.
3. Untuk badan usaha beralias resmi (mis. CSN), sistem menulis judul blok `Gudang {nama} (CSA {alias})`.
4. Sistem menulis header kanonik CSA dan baris data: kode gudang, kode/nama item, serial/IMEI, `Qty Akhir` dari Shelf; kolom `Fisik`, `Selisih`, dan `Keterangan` dikosongkan.
5. Sistem menulis sheet `PETUNJUK` berisi langkah isi audit dan Import.
6. Sistem mengunduh workbook sheet `ASET` siap diisi auditor.

Exception: tidak ada aset yang memenuhi kriteria menghasilkan workbook dengan header saja atau notifikasi kosong sesuai implementasi UI.

Postcondition: tidak ada data Asset yang berubah; file dapat diisi lalu di-Import pada UC-001.

Acceptance: memenuhi FR-019, FR-020, dan NFR-006.

# SRS — Rekonsiliasi Audit Aset CSA ke Shelf

Status: Reviewed

## Tujuan dan aktor

Pengguna berizin `import_asset` dapat membandingkan workbook audit CSA dengan Shelf, meninjau gap, menerapkan koreksi, lalu memverifikasi ulang tanpa kehilangan jejak audit.

## Prinsip sinkronisasi eksternal

Setiap sinkronisasi data dari sistem lain ke Shelf wajib mengikuti pipeline Export → Import → Laporan → Apply. Export menghasilkan workbook format CSA dari saldo Shelf agar pengguna mengisi audit fisik dengan kolom yang sama; Import men-stage sumber; Laporan menampilkan hasil banding (Inline/Gap/Blocked) tanpa mengubah data hidup; Apply baru menulis ke Shelf setelah pengguna meninjau laporan. Sync API atau mutasi langsung ke `Asset` tanpa staging dan laporan dilarang untuk jalur rekonsiliasi eksternal. Import template Shelf reguler tetap jalur terpisah dan tidak diganti.

## Functional requirements

- FR-001 — Sistem menerima `.xlsx/.xls` dan membaca sheet yang dipilih, default `ASET`.
- FR-002 — Sistem mengenali blok header yang tidak seragam dan menormalkan Gudang, Kode Item, Nama Barang/Item, S/N/IMEI, Saldo Sistem, Saldo Real, Koreksi, dan Keterangan.
- FR-003 — Gudang CSA dipetakan ke `AssetLocation.external_code` atau nama lokasi ternormalisasi.
- FR-004 — Barang/Item CSA disimpan sebagai master `AssetCatalogItem`; saldo fisiknya tetap direpresentasikan oleh `Asset` per lokasi.
- FR-005 — Target Shelf adalah Saldo Real bila tersedia; jika kosong, target adalah Saldo Sistem + Koreksi.
- FR-006 — Baris unit tanpa serial dengan Gudang dan Item sama diagregasi sebelum compare.
- FR-007 — Pencocokan berurutan: serial/IMEI, Kode Item + Lokasi, lalu Nama + Lokasi.
- FR-008 — Preview menampilkan saldo CSA, audit fisik, koreksi, saldo Shelf, target, gap, strategi match, aksi, dan alasan blokir.
- FR-009 — Serial duplikat, kuantitas pecahan/negatif/tidak konsisten, dan kandidat berserial ambigu diblokir dari apply.
- FR-010 — Apply memperbarui atau membuat data secara atomik dan idempotent; batch yang sama tidak boleh diterapkan dua kali.
- FR-011 — Target nol tidak menghapus aset dan tidak mengubah kondisi operasional; `qty=0` dan `inventory_active=false` menjaga riwayat.
- FR-012 — Setiap perubahan menyimpan snapshot sebelum/sesudah, pengguna, waktu, workbook hash, dan baris sumber.
- FR-013 — Compare ulang membuat batch anak immutable dan tidak menimpa bukti batch sebelumnya.
- FR-014 — Lokasi tidak dikenal dapat dibuat hanya saat apply jika opsi tersebut diaktifkan.
- FR-015 — Setiap batch wajib memilih `BusinessEntity` default resmi untuk baris tanpa marker; target per baris dipilih berurutan dari override Gudang, marker badan usaha eksplisit pada workbook, lalu default batch.
- FR-016 — Marker `CSN` dipetakan secara exact ke master `PT. COMPLETE SOLUSI NUSANTARA`; marker yang tidak memiliki alias resmi atau master exact wajib menghasilkan status Blocked dan tidak boleh ditebak dari nama/kode lokasi.
- FR-017 — Setiap item staging menyimpan marker eksternal dan `business_entity_id` targetnya sendiri; apply menggunakan target per item dan menyimpannya dalam snapshot sebelum/sesudah.
- FR-018 — Sinkronisasi eksternal selalu melewati tahap berurutan: Export format CSA (opsional tetapi disarankan), Import (unggah/parse/stage), Laporan (preview banding non-mutating), lalu Apply (mutasi atomik setelah konfirmasi). Import/Laporan/Apply tidak boleh dilewati; Apply tidak boleh sebelum Laporan.
- FR-019 — Sistem mengekspor workbook sheet `ASET` dengan header kanonik CSA (`Kode Gudang`, `Kode Item`, `Nama Item`, `S/N`, `Qty Akhir`, `Fisik`, `Selisih`, `Keterangan`) dari saldo Shelf aktif per lokasi.
- FR-020 — Export mengisi `Qty Akhir` dari `Asset.qty`, mengosongkan `Fisik`/`Selisih`/`Keterangan` untuk diisi auditor, menyertakan kode gudang (`external_code` atau nama), kode/nama item dari katalog bila ada, serial/IMEI bila ada, dan marker `(CSA {alias})` untuk badan usaha yang punya alias resmi.

## Non-functional requirements

- NFR-001 — Apply harus menggunakan transaksi database dan row lock.
- NFR-002 — Workbook sampai sedikitnya 1.000 baris harus dapat dipreview dalam satu permintaan operasional normal.
- NFR-003 — Hanya pengguna dengan izin import aset yang dapat mengakses fitur.
- NFR-004 — Tidak ada penghapusan `Asset` akibat rekonsiliasi.
- NFR-005 — Audit trail batch yang telah diterapkan bersifat immutable.
- NFR-006 — Fitur sinkronisasi eksternal baru wajib memakai pola staging + laporan sebelum mutasi; tidak boleh menambah jalur tulis langsung dari sumber eksternal ke data operasional.

## Acceptance summary

Skenario utama: pengguna mengekspor format CSA dari Shelf, mengisi Fisik/Selisih, lalu Import & Laporan. Default Retail; baris `(CSA CSN)` menargetkan master CSN exact. Saldo Shelf 2, audit fisik 1, koreksi -1 menghasilkan preview gap -1; setelah apply qty dan badan usaha per item sesuai; compare ulang Inline. Marker tidak dikenal dan batch tanpa default diblokir.

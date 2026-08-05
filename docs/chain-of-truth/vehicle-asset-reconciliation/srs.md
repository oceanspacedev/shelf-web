# SRS — Rekonsiliasi Audit Kendaraan ke Shelf

Status: Reviewed

## Tujuan dan aktor

Pengguna berizin `import_asset` membandingkan workbook audit kendaraan (Monitoring Asset / LHP) dengan Shelf, meninjau gap, menerapkan koreksi fleet, lalu memverifikasi ulang tanpa menghapus riwayat aset.

## Prinsip sinkronisasi

Setiap sinkronisasi eksternal ke Shelf wajib Export → Import → Laporan → Apply. Berbagi prinsip dengan rekonsiliasi CSA; sumber dan aksi berbeda.

## Functional requirements

- FR-VA-001 — Sistem menerima `.xlsx/.xls` dan membaca sheet yang dipilih, default `Monitoring Asset`.
- FR-VA-002 — Parser mengenali header Monitoring Asset dan menormalkan plat, nama, STNK, ACC, keberadaan, merk/tipe, rangka/mesin, pemegang, BPKB.
- FR-VA-003 — Disposisi `sold` bila ACC atau nama STNK mengandung `TERJUAL` (case-insensitive); selain itu `active`.
- FR-VA-004 — Identitas match: plat ternormalisasi dari attribute `Plat Nomor`, fallback parse dari nama/serial.
- FR-VA-005 — Multi-match plat menghasilkan satu keep (skor kelengkapan tertinggi) dan kandidat `retire_duplicate` untuk sisanya.
- FR-VA-006 — Preview menampilkan Inline/Gap/Blocked dengan aksi `mark_sold`, `retire_duplicate`, `create_missing`, `enrich`, `none`.
- FR-VA-007 — Entity target per baris: alias ACC/STNK exact dari konfigurasi, lalu nama STNK exact di master, lalu default batch; tidak dikenal → Blocked.
- FR-VA-008 — Apply atomik dan idempotent; batch applied immutable; Compare Ulang membuat batch anak.
- FR-VA-009 — `mark_sold` menulis `condition_status=sold`, `sold_at`, `sold_to`, `sold_price` (0 bila tidak diketahui), `is_available=false`; tidak menghapus baris.
- FR-VA-010 — `retire_duplicate` menulis `inventory_active=false` pada kandidat non-keep; tidak hard-delete.
- FR-VA-011 — `create_missing` hanya saat opsi create aktif (`auto_create_locations` dipakai ulang sebagai flag create aset kendaraan); membuat Asset MOBIL/MOTOR + attribute Plat Nomor.
- FR-VA-012 — `enrich` mengisi plat/serial/nama kosong pada aset keep yang cocok.
- FR-VA-013 — Aset Shelf tanpa pasangan audit tidak dihapus otomatis; dicatat di summary observasi.
- FR-VA-014 — Export menghasilkan sheet `Monitoring Asset` kanonik dari aset MOBIL/MOTOR aktif/fleet + sheet `PETUNJUK`.
- FR-VA-015 — `source_system` batch = `VEHICLE_AUDIT`; terpisah dari CSA pada UI dan service.
- FR-VA-016 — Master custom attribute `STNK` dan `KIR` bertipe `document_expiry` wajib tersedia untuk kategori MOBIL/MOTOR dengan pengingat relative (default H-30).
- FR-VA-017 — Import membaca `EXPIRED STNK` dari Monitoring Asset dan `Validity` dari sheet `KIR`, lalu Apply menulis/memperbarui `AssetAttribute` dokumen (expires_at + nomor plat) tanpa menghapus lampiran yang sudah ada.
- FR-VA-018 — Apply juga menyinkronkan Pajak, Asuransi (polis/expired/notes), BPKB (status+nomor), Pemegang Inventaris (teks; `recipient_id` hanya jika nama user exact unik), dan lokasi dari CEK KEBERADAAN (alias atau create-on-apply).

## Non-functional requirements

- NFR-VA-001 — Apply memakai transaksi dan row lock.
- NFR-VA-002 — Hanya pengguna dengan izin import aset.
- NFR-VA-003 — Tidak ada hard-delete Asset dari rekonsiliasi.
- NFR-VA-004 — Audit trail batch applied immutable.

## Acceptance summary

Import workbook Monitoring Asset → laporan menandai sold masih aktif, double plat, dan missing; setelah Apply tanpa Blocked, sold menjadi `sold`, duplicate `inventory_active=false`, missing tercipta bila diizinkan; Compare Ulang → Inline untuk baris yang sudah selaras.

# UC-002 — Terapkan Koreksi

Status: Reviewed

Trigger: pengguna mengonfirmasi `3. Terapkan Koreksi` setelah meninjau Laporan.

Precondition: Import dan Laporan selesai; batch compared, setiap item Gap memiliki badan usaha target resmi, belum applied, dan tidak memiliki baris terblokir.

Main path:

1. Sistem mengunci batch, item, dan kandidat Asset dalam transaksi.
2. Sistem membuat/memperbarui master Barang/Item dan lokasi yang diizinkan.
3. Sistem membuat Asset surplus atau mengubah qty/lokasi/katalog kandidat.
4. Untuk target nol, sistem mempertahankan Asset dan menandai inventori nonaktif.
5. Sistem menyimpan snapshot sebelum/sesudah dan menandai batch Applied.

Exception: kandidat berubah/hilang atau batch sudah diterapkan menyebabkan rollback penuh.

Postcondition: data Shelf mengikuti target audit dan `business_entity_id` target masing-masing item untuk semua gap yang aman.

Acceptance: memenuhi FR-010 sampai FR-012, FR-015, FR-018, dan NFR-001, NFR-004, NFR-005, NFR-006.

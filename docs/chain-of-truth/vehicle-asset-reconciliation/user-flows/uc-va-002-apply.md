# UC-VA-002 — Apply Koreksi Kendaraan

Status: Reviewed

Trigger: pengguna mengonfirmasi Terapkan Koreksi setelah meninjau laporan tanpa Blocked.

Precondition: status Compared/Aligned; `gap_rows > 0`; `blocked_rows = 0`.

Main path: transaksi menerapkan aksi Gap; snapshot before/after disimpan; batch menjadi Applied.

Postcondition: sold/duplicate/create/enrich sesuai aksi; tidak ada hard-delete.

Acceptance: FR-VA-008–012, NFR-VA-001, NFR-VA-003.

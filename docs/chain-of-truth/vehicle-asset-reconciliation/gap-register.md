# Gap Register — Audit Kendaraan

Status: Reviewed

| ID | Classification | Gap | Mitigation | Residual decision |
|---|---|---|---|---|
| GAP-VA-001 | UX clarity | Copy recompare/laporan memakai istilah CSA (Gudang/Marker CSA) untuk batch VEHICLE_AUDIT. | Branch label/modal/infolist saat `source_system=VEHICLE_AUDIT` (Keberadaan, Marker ACC, retired = Dinonaktifkan/Terjual). | Closed untuk polish UI. |
| GAP-VA-002 | Missing visibility | `summary.orphan_shelf_plates` dan `summary.actions` dihitung tetapi tidak tampil di laporan. | TextEntry ringkasan aksi + orphan di infolist (FR-VA-013). | Closed. |
| GAP-VA-003 | Data ownership | Alias ACC/lokasi di config tidak cocok dengan seeder lokal (nama pendek). | Seeder + migrasi `2026_08_03_000003_ensure_vehicle_audit_master_aliases`; command `vehicle-audit:validate-masters` exit non-zero bila target hilang. | Staging/prod wajib jalankan validate-masters sebelum Apply massal. |
| GAP-VA-004 | Safety default | Toggle create default true — berisiko create aset/lokasi tanpa sadar. | Default create **off** untuk VEHICLE_AUDIT; helper menjelaskan create aset + lokasi saat Apply. | Closed; create tetap opt-in. |
| GAP-VA-005 | Verification | Edge FR (unknown ACC, create-off, idempotent apply, orphan, lokasi, recipient) belum terisolasi. | TC-VA-009–014 + export↔parser round-trip. | Closed di automated suite. |
| GAP-VA-006 | Fixture policy | LHP asli berisi PII/operasi — tidak masuk git. | Fixture sintetis `tests/fixtures/vehicle-audit-sample.xlsx`; staging dry-run memakai file lokal di luar repo. | UAT staging dengan LHP nyata tetap Pending manusia. |
| GAP-VA-007 | Out of scope | Gap Accounting↔GA di luar Shelf. | Baseline menolak menyelesaikan lewat rekonsiliasi. | Tidak diubah. |

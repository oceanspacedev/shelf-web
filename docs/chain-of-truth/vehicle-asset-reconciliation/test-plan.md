# Test Plan — Audit Kendaraan

Status: Reviewed

Lingkup: parser Monitoring Asset, compare/apply/recompare VEHICLE_AUDIT, export format, cabang UI service.

Strategi: unit parser + feature service dengan schema sqlite in-memory dan fixture workbook kecil. Tidak memakai file Downloads di CI.

Exit: parser mengenali sold/plat; compare menandai double dan sold; apply idempotent tanpa delete; recompare Inline; unknown ACC / create-off Blocked; orphan terhitung; validate-masters hijau; export↔parser round-trip.

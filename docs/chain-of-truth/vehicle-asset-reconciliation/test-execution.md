# Test Execution — Audit Kendaraan

Status: Reviewed

Evidence date: 2026-08-03 (maturity hardening).

## Automated

Command:

```bash
php artisan test --filter=VehicleAsset
```

Result: **14 passed**, 80 assertions.

Coverage exercised:

| ID | Evidence |
|---|---|
| TC-VA-001–008 | Parser unit, exporter, happy-path compare→apply→recompare |
| TC-VA-009 | Unknown ACC → Blocked |
| TC-VA-010 | Create flag off → missing plate Blocked |
| TC-VA-011 | Second apply rejected |
| TC-VA-012 | orphan_shelf_plates counted |
| TC-VA-013 | Location alias sync on apply |
| TC-VA-014 | Unique holder → recipient_id |
| Round-trip | Export Monitoring Asset → parser preserves plate |
| Ops gate | `vehicle-audit:validate-masters` pass + fail paths |

## Staging checklist (manual, no PII in git)

1. `php artisan migrate` (atribut STNK/KIR/operasional).
2. `php artisan vehicle-audit:validate-masters` — harus exit 0.
3. Export Format Audit Kendaraan dari List Assets.
4. Import workbook LHP staging (`source_system=VEHICLE_AUDIT`), create **off** dulu.
5. Pastikan `blocked_rows` ditinjau; Apply hanya jika 0 Blocked.
6. Apply batch kecil → Compare Ulang → baris yang dikoreksi Inline.
7. Baru ulangi dengan create on bila plat missing memang harus dibuat.

Browser UAT dengan user berizin `import` Asset: Pending stakeholder.

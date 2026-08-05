# Data Model — Rekonsiliasi Audit Kendaraan

Status: Reviewed

Reuse entitas rekonsiliasi CSA dengan semantik kendaraan:

- ENT-VA-001 `asset_reconciliations.source_system=VEHICLE_AUDIT`; `source_sheet` default `Monitoring Asset`; `auto_create_locations` = flag buat aset baru untuk plat missing.
- ENT-VA-002 `asset_reconciliation_items` reuse kolom:
  - `external_item_code` = plat ternormalisasi
  - `item_name` = nama kendaraan audit
  - `serial_number` = no rangka
  - `external_location_code` = keberadaan (hint)
  - `external_business_entity_code` = ACC/marker
  - `target_qty` = 0 sold / 1 active
  - `shelf_qty` = 1 bila ada aset aktif inventory untuk plat, else 0
  - `raw_payload` = kolom audit penuh + disposition
- ENT-VA-003 `assets` + `asset_attributes` (`Plat Nomor`, `STNK`, `KIR`, `Pajak`, `Asuransi`, `BPKB`, `Pemegang Inventaris`) + kategori MOBIL/MOTOR.
- ENT-VA-004 Snapshot before/after mencakup `condition_status`, `inventory_active`, plat, serial, entity, lokasi.
- ENT-VA-005 Dokumen `STNK` / `KIR` / `Pajak` / `Asuransi` bertipe `document_expiry` (JSON expires_at/number/notes); pengingat H-30.
- ENT-VA-006 `BPKB` dan `Pemegang Inventaris` bertipe text; `recipient_id` diisi hanya pada exact name match unik.

Kardinalitas: satu batch banyak item; satu plat dapat banyak Asset kandidat; applied batch tidak diperbarui ulang.

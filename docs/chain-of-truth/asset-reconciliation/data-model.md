# Data Model — Rekonsiliasi Aset

Status: Reviewed

- ENT-001 `asset_catalog_items`: identitas master Barang/Item per `source_system` dan `external_code`; nama ternormalisasi mendukung fallback.
- ENT-002 `asset_locations.external_code`: mapping Gudang CSA ke Lokasi Shelf.
- ENT-003 `assets.asset_catalog_item_id`: hubungan saldo Asset ke master Item.
- ENT-004 `assets.inventory_active`: membedakan saldo inventori nol dari kondisi operasional/NBH.
- ENT-005 `asset_reconciliations`: batch, parent, `business_entity_id` default, JSON `business_entity_mappings` per Gudang, file hash, status, actor, hitungan, waktu compare/apply.
- ENT-006 `asset_reconciliation_items`: baris sumber, `external_business_entity_code`, `business_entity_id` target, saldo, target, gap, kandidat, strategi, aksi, pesan, dan snapshot.

Kardinalitas: satu katalog memiliki banyak Asset; satu lokasi memiliki banyak Asset; satu badan usaha memiliki banyak batch dan Asset; satu batch memiliki banyak item; satu batch dapat memiliki banyak batch verifikasi anak. Batch applied tidak diperbarui ulang.

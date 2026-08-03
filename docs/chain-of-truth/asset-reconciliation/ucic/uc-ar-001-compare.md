# API-001 — Compare Contract

Status: Reviewed

Use case: UC-001.

Interface: `AssetReconciliationService::compare(AssetReconciliation): AssetReconciliation`.

Input: `business_entity_id` default, optional `business_entity_mappings` per Gudang, path file, source system, source sheet, dan opsi lokasi. Parser mengeluarkan `external_business_entity_code`. Resolver memakai override Gudang, lalu alias marker exact dari konfigurasi, lalu default. Alias/master yang tidak tersedia memblokir item. Output menyimpan target badan usaha per item; side effect hanya staging dan tidak mengubah Asset.

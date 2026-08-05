# API-VA-003 — Recompare Contract

Status: Reviewed

Use case: UC-VA-003.

Interface: `VehicleAssetReconciliationService::recompare(AssetReconciliation, ?userId, ?businessEntityId, ?mappings): AssetReconciliation`.

Membuat batch anak lalu `compare()`. Batch induk tidak diubah.

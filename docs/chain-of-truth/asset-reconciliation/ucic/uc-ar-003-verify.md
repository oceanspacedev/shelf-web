# API-003 — Recompare Contract

Status: Reviewed

Use case: UC-003.

Interface: `AssetReconciliationService::recompare(AssetReconciliation, ?int $userId, ?int $businessEntityId): AssetReconciliation`.

Output adalah batch baru dengan `parent_id`, default `business_entity_id`, JSON override Gudang, dan file hash yang sama. Parameter mapping null mewarisi mapping induk; array kosong menghapus override. Interface menjalankan API-001 dan tidak menimpa batch induk.

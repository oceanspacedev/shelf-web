# API-VA-001 — Compare Contract

Status: Reviewed

Use case: UC-VA-001.

Interface: `VehicleAssetReconciliationService::compare(AssetReconciliation): AssetReconciliation`.

Input: batch `source_system=VEHICLE_AUDIT`, path file, sheet, `business_entity_id`, flag create. Output: item staging Inline/Gap/Blocked. Side effect: staging only.

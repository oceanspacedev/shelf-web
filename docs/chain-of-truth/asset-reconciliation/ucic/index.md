# UCIC Index

Status: Reviewed

Urutan kontrak mengikuti FR-018: Export format CSA, Import/Laporan (`compare`), Apply (`apply`), lalu verifikasi (`recompare`).

- UC-000 / API-000 — `CsaAssetAuditWorkbookExporter::exportToPath` (Export format CSA; non-mutating).
- UC-001 / API-001 — `AssetReconciliationService::compare` (Import + Laporan; non-mutating).
- UC-002 / API-002 — `AssetReconciliationService::apply` (mutasi hanya setelah Laporan aman).
- UC-003 / API-003 — `AssetReconciliationService::recompare` (Laporan verifikasi batch anak).

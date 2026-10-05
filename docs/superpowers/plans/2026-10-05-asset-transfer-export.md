# Asset Transfer Export Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox syntax.

**Goal:** Filament XLSX export for asset transfers, one row per transfer detail line.

**Architecture:** `AssetTransferDetailExporter` + `ExportAction` on `ListAssetTransfers`, permission `export_asset::transfer`.

**Tech Stack:** Laravel, Filament ExportAction/Exporter, Spatie Permission, PHPUnit

## Global Constraints

- Mirror `AssetExporter` / `ListAssets` patterns (queue `exports`, XLSX, `__invoke` column alignment)
- One export row = one `AssetTransferDetail`
- Do not commit unless user asks

---

### Task 1: Exporter + list action + permission

- [ ] Add `app/Filament/Exports/AssetTransferDetailExporter.php`
- [ ] Wire `ExportAction` in `ListAssetTransfers`
- [ ] Add `export()` to `AssetTransferPolicy`
- [ ] Register Shield manage export for `AssetTransferResource`
- [ ] Migration for `export_asset::transfer` permission grants
- [ ] Tests mirroring `AssetExporterTest` + Shield assertion
- [ ] Run relevant PHPUnit tests

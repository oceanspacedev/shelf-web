# Asset Transfer Export — Design Spec

**Date:** 2026-10-05  
**Status:** Approved for implementation

## Goal

Tambah export Filament native di list Asset Transfers, pola sama dengan Asset (`ExportAction` + Exporter + queue `exports`).

## Decisions

| Topic | Choice |
|-------|--------|
| Mekanisme | Filament `ExportAction` + `Exporter` |
| Grain baris | Satu baris per detail aset (bukan per BA) |
| Format | XLSX only (seperti Asset) |
| Queue | `exports` |
| Permission | `export_asset::transfer` via policy `export` + Shield `manage` |

## Architecture

- `AssetTransferDetailExporter` — model `AssetTransferDetail`
- `ListAssetTransfers` — header `ExportAction` (visible jika `can('export', AssetTransfer::class)`)
- `modifyQuery` di exporter mengonversi filtered `AssetTransfer` query → `asset_transfer_details` + eager load
- Migration grant permission ke role yang mengelola transfer
- Shield config: `AssetTransferResource` → `export`

## Columns (per baris detail)

Nomor BA, Badan Usaha, Status, Dari Pengguna, Ke Pengguna, Tanggal Transfer, Nama Aset, Serial Number, Keterangan Peralatan, Dokumen (URL atau `-`)

## Out of scope

- Import
- Audit workbook formats
- Export bulk action khusus

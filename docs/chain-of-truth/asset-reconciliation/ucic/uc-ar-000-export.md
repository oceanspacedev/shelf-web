# API-000 — Export Format CSA Contract

Status: Reviewed

Interface: `CsaAssetAuditWorkbookExporter::exportToPath(?string $path = null): string`.

Authorization di UI melalui izin import atau export Asset. Tidak ada side effect pada `Asset`. Output: path file `.xlsx` sheet `ASET` dengan header kanonik yang dapat dibaca `CsaAssetAuditWorkbookParser`. Field mapping: lokasi → `Kode Gudang`; katalog/`Asset.name` → `Kode Item`/`Nama Item`; serial atau IMEI → `S/N`; `qty` → `Qty Akhir`; `Fisik`/`Selisih`/`Keterangan` kosong; alias badan usaha → marker `(CSA {alias})` pada judul blok gudang.

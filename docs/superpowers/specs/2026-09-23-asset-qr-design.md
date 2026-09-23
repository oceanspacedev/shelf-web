# Asset QR Codes — Design Spec

**Date:** 2026-09-23  
**Status:** Draft — awaiting user review before implementation plan  
**Inspired by:** [shelf.nu](https://github.com/Shelf-nu/shelf.nu) QR asset tags (adapted for Laravel/Filament shelf-web)

## Goal

Every asset has a QR code. Scanning with a phone camera opens a **public** page (no login) that shows where the asset is and how many (qty), plus other non-sensitive detail similar to the admin View Asset page. Admins can download a single label or bulk-print labels. Each scan is recorded with optional GPS.

## Decisions (from product discussion)

| Topic | Choice |
|-------|--------|
| Scan auth | Public — no login required |
| Public content | Like View Asset, without price / sensitive documents |
| Primary scan signal | **Location** (and qty) first |
| Print | Single download from View Asset **and** bulk from List Assets |
| Orphan / pre-print stickers | **No** — 1 asset = 1 QR, auto on create |
| Scan history | Yes, with GPS when browser allows |
| In-app Filament scanner | **No** — phone camera + public URL only |
| Existing assets | Backfill via artisan command after deploy |

## Out of scope

- Unclaimed / orphan QR batches and claim flows
- In-app camera scanner in Filament
- Kits (shelf.nu kits)
- Remapping one printed sticker across multiple assets without regenerate
- WhatsApp bot changes (existing `asset_tag` = DB id remains as-is)

## Architecture

### Approach

Separate `asset_qrs` table (shelf.nu-style), not a column on `assets` and not Filament admin URLs in the QR payload.

- QR encodes: `{APP_URL}/qr/{qr.id}`
- `qr.id` is an opaque ULID/UUID string (not the numeric asset id)
- One active QR per asset (`asset_id` unique)

```
Asset created ──► AssetQrService::createForAsset()
                      │
                      ▼
                 asset_qrs row
                      │
Phone camera scan ──► GET /qr/{id} ──► show public blade
                      │                 │
                      │                 └─► AssetQrScanService::record()
                      │                       (+ optional lat/lng via follow-up POST)
                      ▼
                 View Asset / List: preview, download, bulk PDF
```

### Data model

**`asset_qrs`**

| Column | Type | Notes |
|--------|------|-------|
| id | string ULID/UUID, PK | Encoded in QR URL |
| asset_id | FK → assets, unique | One QR per asset |
| created_at / updated_at | timestamps | |

**`asset_qr_scans`**

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| asset_qr_id | FK → asset_qrs | Cascade on QR delete |
| latitude | string/decimal nullable | From browser geolocation |
| longitude | string/decimal nullable | |
| user_agent | text nullable | |
| user_id | FK users nullable | Set only if session already authenticated |
| created_at / updated_at | timestamps | |

Indexes: `asset_qrs.asset_id` unique; `asset_qr_scans.asset_qr_id`; optional `(asset_qr_id, created_at)`.

### Services

- **`AssetQrService`**
  - `createForAsset(Asset): AssetQr`
  - `ensureForAsset(Asset): AssetQr` (idempotent; used by backfill)
  - `regenerate(Asset): AssetQr` (delete/replace old QR; old URL becomes invalid)
  - `publicUrl(AssetQr): string`
  - `png(AssetQr, size): binary` via QR library (e.g. `endroid/qr-code`)
- **`AssetQrScanService`**
  - `record(AssetQr, ?user, ?userAgent, ?lat, ?lng): AssetQrScan`

### Lifecycle hooks

- On `Asset` **created**: create QR (observer or model event).
- Artisan `assets:generate-missing-qrs`: backfill all assets without a QR.
- On QR **regenerate**: old row removed (or soft-replaced); scans may cascade-delete with old QR or be retained only if we soft-delete — **choice: hard replace QR row, cascade delete old scans** (simpler; regenerate is rare admin action).

### Public routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/qr/{qr}` | Resolve QR → asset; record scan (no coords yet); render public page |
| POST | `/qr/{qr}/location` | Accept `{latitude, longitude}` after browser geolocation; update latest scan or create location-only update on last scan for this session |

CSRF: use Laravel web middleware; public page includes CSRF token for the location POST, or use a signed short-lived token. Prefer standard web CSRF on the Blade page.

No auth middleware on these routes.

### Public page content

**Emphasize first**

1. Asset name + condition/status badge  
2. **Location** (asset location name; business entity if useful)  
3. **Qty**

**Also show (non-sensitive View Asset parity)**

- Category, brand, type  
- Serial / IMEI when present  
- Image  
- Recipient name (not phone/email if those exist elsewhere)  
- Custom attributes that are not file/document types  
- NBH status label (text only — no document path)

**Never show on public page**

- `item_price`, `sold_price`, sale audit money fields  
- Document file paths / download links (STNK, KIR, NBH docs)  
- Internal reconciliation IDs unless already public elsewhere  

**Errors**

- Unknown QR → 404 Blade “Kode tidak valid”  
- QR exists but asset missing → same invalid page  

### Admin UI (Filament)

**View Asset**

- QR section: preview image, QR id, copy/open public URL  
- Actions: Download label (PNG or PDF), Regenerate QR (`super_admin` / `general_affair`)  
- Relation or simple table: **Riwayat Scan** (datetime, lat/lng if any, user agent truncated)

**List Assets**

- Bulk action **Cetak label QR** → DomPDF grid of labels (name + QR image + short location)  
- Default label size: medium; small/large can be a later enhancement if needed in v1 as a simple select  

**Permissions**

- View/download/print: same as viewing Asset  
- Regenerate: `super_admin`, `general_affair`

### Print / PDF

Reuse existing `barryvdh/laravel-dompdf`. Label blade includes:

- Asset name  
- QR PNG (embedded)  
- Location short text  
- Optional QR id under code for support  

Single download can be PNG from `AssetQrService::png` or one-up PDF; bulk is multi-label PDF.

### Libraries

- Add a maintained PHP QR package (prefer `endroid/qr-code` unless project already has another).  
- DomPDF already present for transfer PDFs.

### Testing

- Creating an asset creates exactly one `asset_qrs` row.  
- `ensureForAsset` / backfill does not duplicate.  
- `GET /qr/{id}` returns 200 and includes location + qty; response does not include price fields.  
- Scan row created on GET; location POST stores coordinates when provided.  
- Regenerate: old id 404, new id 200.  
- Bulk print action only for selected assets; generates PDF successfully when QR exists.  
- Feature/unit tests under `tests/`.

### Deployment steps

1. Migrate `asset_qrs` + `asset_qr_scans`  
2. `composer require` QR package  
3. `php artisan assets:generate-missing-qrs`  
4. Verify one existing asset View page shows QR; phone scan opens public page  

## Success criteria

- Every asset (old after backfill, new on create) has a scannable QR.  
- Phone camera scan shows location and qty without logging in.  
- Admins can download one label and bulk-print labels.  
- Scans appear in admin history with GPS when permitted.

## Open implementation notes (non-blocking)

- Exact QR package version pinned at implementation time.  
- Whether single label download is PNG-only or PDF-only: implement PDF label for consistency with bulk; PNG optional if trivial.  
- Geolocation UX: ask on page load with a short Indonesian notice; never block page render if denied.

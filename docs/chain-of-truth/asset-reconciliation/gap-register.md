# Gap Register

Status: Reviewed

| ID | Classification | Gap | Mitigation | Residual decision |
|---|---|---|---|---|
| GAP-001 | Unknown intent | Kode gudang CSA belum tentu sudah dipetakan ke lokasi Shelf. | `external_code`, preview non-mutating, dan opsi create-on-apply. | Stakeholder meninjau lokasi baru setelah import pertama. |
| GAP-002 | Source ambiguity | Barang dan Item dipakai bergantian. | Master `AssetCatalogItem` memegang identitas eksternal; Asset memegang saldo lokasi. | Stakeholder mengonfirmasi istilah UI. |
| GAP-003 | Missing identity | Sebagian baris tidak memiliki serial dan ditulis per unit. | Agregasi Gudang + Item sebelum compare. | Baris berserial ambigu tetap diblokir. |
| GAP-004 | Validation | Dokumen belum divalidasi stakeholder. | Semua artifact berstatus Reviewed dan implementasi diuji; produksi menunggu approval. | Pending. |
| GAP-005 | Data ownership | Batch awal diterapkan tanpa target badan usaha per item: 563 dari 565 Asset audit kosong dan workbook ternyata mencampur Retail dengan CSN. | Default batch + marker CSA exact + override Gudang; target disimpan per item dan apply menyimpan snapshot. | Marker CSN tervalidasi; kode lain seperti CVCS belum boleh dipetakan tanpa konfirmasi/override resmi. |
| GAP-006 | Ambiguous identity | Preview Retail memiliki 35 baris agregat yang cocok ke lebih dari satu Asset berserial. | Status Blocked dan apply seluruh batch ditolak. | Pengguna harus menyelesaikan mapping/identitas manual sebelum apply. |
| GAP-007 | Architecture rule | Sinkronisasi eksternal berisiko ditulis sebagai mutasi langsung tanpa staging. | FR-018 / NFR-006 mengunci pipeline Export → Import → Laporan → Apply; UI memisahkan Import reguler dari rekonsiliasi. | Closed untuk CSA; berlaku sebagai pola wajib sync sumber lain ke depan. |
| GAP-008 | UX clarity | Urutan sync mudah terlewati jika label UI tidak menomori tahap. | Label `0 Export` / `1 Import` / `2 Laporan` / `3 Apply`, subheading, Apply disabled saat Blocked, sheet PETUNJUK pada export. | Closed untuk polish UI; UAT browser tetap Pending. |

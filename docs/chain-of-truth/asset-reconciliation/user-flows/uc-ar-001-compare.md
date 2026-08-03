# UC-001 — Upload dan Compare

Status: Reviewed

Trigger: pengguna memilih `1. Import Audit CSA`.

Precondition: pengguna memiliki izin `import_asset`; workbook berisi sheet aset.

Main path:

1. Pengguna memilih badan usaha default resmi dari master, mengunggah workbook, dan memilih sheet (tahap Import).
2. Sistem menyimpan file dan SHA-256.
3. Parser mendeteksi setiap header blok dan menormalkan data.
4. Baris tanpa serial diagregasi per Gudang + Item.
5. Sistem memetakan target badan usaha per item dengan prioritas override Gudang → marker CSA exact → default batch.
6. Sistem memetakan lokasi, katalog, dan kandidat Asset serta membandingkan `business_entity_id` terhadap target per item.
7. Sistem memblokir batch tanpa default dan marker yang belum memiliki mapping resmi; Asset yang kosong atau berbeda badan usaha menjadi Gap.
8. Sistem menyimpan hasil Inline, Gap, atau Terblokir dan menampilkan PAGE-004 sebagai Laporan.

Exception: sheet hilang atau tidak ada baris valid membuat batch Failed. Kuantitas tidak valid dan match ambigu menjadi Terblokir.

Postcondition: belum ada data Asset yang berubah; Apply belum tersedia sampai Laporan ditinjau dan tidak ada baris Blocked.

Acceptance: memenuhi FR-001 sampai FR-009, FR-015 sampai FR-018, dan NFR-003, NFR-006.

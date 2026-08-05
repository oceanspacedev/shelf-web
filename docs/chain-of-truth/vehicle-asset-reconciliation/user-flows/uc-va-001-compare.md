# UC-VA-001 — Import dan Compare

Status: Reviewed

Trigger: pengguna memilih source `VEHICLE_AUDIT` lalu mengunggah workbook.

Precondition: izin `import_asset`; sheet Monitoring Asset valid; badan usaha default dipilih.

Main path:

1. Sistem stage file + SHA-256.
2. Parser menormalkan baris Monitoring Asset.
3. Sistem match plat ke Asset MOBIL/MOTOR.
4. Sistem klasifikasi Inline/Gap/Blocked dan aksi (mark_sold, retire_duplicate, create_missing, enrich).
5. Laporan ditampilkan tanpa mutasi Asset.

Exception: sheet/header invalid → Failed. Entity tidak dipetakan atau ambiguitas tak terselesaikan → Blocked.

Postcondition: Asset belum berubah; Apply disabled jika ada Blocked.

Acceptance: FR-VA-001–007, FR-VA-015.

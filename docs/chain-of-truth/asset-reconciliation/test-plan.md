# Test Plan

Status: Reviewed

Scope: export format CSA (round-trip parser), parser workbook, normalisasi header, perhitungan target, agregasi, pencocokan, mapping badan usaha eksplisit, deteksi gap badan usaha, apply atomik, target nol, pembuatan surplus, idempotensi, dan compare ulang.

Strategy: unit test untuk parser; feature test dengan SQLite untuk model dan service; parser dijalankan read-only terhadap workbook audit nyata; full regression suite; pemeriksaan route/Filament; migration pretend.

Exit criteria: seluruh TC-001 sampai TC-015 lulus, workbook nyata membedakan marker CSN, route resource terdaftar, dan tidak ada regression test baru yang gagal.

# User Flow Index

Status: Reviewed

Pipeline resmi sinkronisasi eksternal: Export → Import → Laporan → Apply. UC di bawah memetakan tahap tersebut; sync baru dari sistem lain wajib mengikuti urutan yang sama.

- UC-000 — Export Format CSA (workbook audit dari saldo Shelf).
- UC-001 — Upload dan Compare (Import + Laporan awal).
- UC-002 — Terapkan Koreksi (Apply setelah Laporan ditinjau).
- UC-003 — Compare Ulang (Laporan verifikasi / batch anak).

# UC-VA-000 — Export Format Kendaraan

Status: Reviewed

Trigger: pengguna memilih Export Format Audit Kendaraan.

Precondition: izin import/export aset; ada aset MOBIL/MOTOR.

Main path: sistem mengekspor workbook sheet `Monitoring Asset` berisi plat, nama, entity, status Shelf, lokasi, pemegang, serial; kolom ACC/keberadaan/BPKB kosong untuk diisi auditor; sheet `PETUNJUK` menjelaskan alur.

Postcondition: file terunduh; data Shelf tidak berubah.

Acceptance: FR-VA-014.

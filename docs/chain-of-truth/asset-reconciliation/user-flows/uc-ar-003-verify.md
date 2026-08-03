# UC-003 — Compare Ulang

Status: Reviewed

Trigger: pengguna memilih `Compare Ulang`.

Precondition: batch sebelumnya sudah memiliki hasil compare; badan usaha master masih tersedia.

Main path:

1. Sistem menampilkan default badan usaha serta daftar Gudang, marker CSA, dan override opsional.
2. Pengguna memilih default dan mengisi override hanya untuk Gudang yang berbeda.
3. Sistem membuat batch anak dengan workbook, hash, default, dan mapping Gudang tersebut.
4. Sistem menjalankan kembali UC-001 terhadap keadaan Shelf terbaru.
5. Pengguna melihat Inline/Gap/Blokir baru tanpa mengubah bukti batch induk atau Asset.

Postcondition: batch induk tetap immutable; verifikasi memiliki identitas dan timestamp sendiri.

Acceptance: memenuhi FR-013, FR-015, dan NFR-005.

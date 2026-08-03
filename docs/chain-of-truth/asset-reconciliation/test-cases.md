# Test Cases

Status: Reviewed

- TC-001 — Parser menerima dua varian header dalam satu sheet dan menghasilkan target Saldo Real.
- TC-002 — Saldo pecahan dan persamaan Saldo Sistem + Koreksi yang tidak konsisten menghasilkan validation error.
- TC-003 — Dua kandidat berserial untuk baris agregat tanpa serial menghasilkan status Blocked dan apply ditolak.
- TC-004 — Shelf qty 2, audit qty 1: preview gap -1, apply qty 1, compare ulang Inline.
- TC-005 — Surplus pada gudang tidak dikenal membuat lokasi, katalog, dan Asset ketika opsi aktif.
- TC-006 — Target nol mempertahankan Asset dengan qty 0 dan `inventory_active=false`; apply kedua ditolak.
- TC-007 — Compare ulang membuat batch anak tanpa mengubah batch induk.
- TC-008 — Tiga baris unit tanpa serial untuk Gudang + Item sama menjadi satu saldo target 3.
- TC-009 — Beberapa Asset Shelf tanpa serial dikonsolidasikan dan compare ulang tetap Inline.
- TC-010 — Batch tanpa badan usaha ditolak; pilihan master resmi diteruskan ke Asset baru/terkoreksi dan batch compare ulang.
- TC-011 — Kandidat tanpa serial pada Gudang + Item sama dengan badan usaha berbeda menghasilkan Gap; pilihan batch menjadi target hanya setelah apply dikonfirmasi.
- TC-012 — Batch lama tanpa badan usaha dapat membuat batch compare ulang hanya ketika pengguna memberikan ID master resmi; batch anak menyimpan pilihan tersebut.
- TC-013 — Marker `(CSA CSN)` diteruskan parser dan menargetkan master exact `PT. COMPLETE SOLUSI NUSANTARA`, bukan default Retail.
- TC-014 — Override Gudang mengalahkan marker/default; marker yang tidak dikenal menghasilkan Blocked.
- TC-015 — Apply menggunakan badan usaha target per item dan menolak item staging lama yang belum memiliki mapping.
- TC-016 — Export format CSA menghasilkan sheet `ASET` yang dapat di-parse ulang: `Qty Akhir` dari Shelf, Fisik/Selisih kosong, marker CSN pada blok badan usaha beralias, dan kode gudang/item ikut.

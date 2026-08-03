# Traceability Matrix

Status: Reviewed

| Requirement | Flow | Entities / Interface | Test |
|---|---|---|---|
| FR-001, FR-002, FR-003, FR-004, FR-005, FR-006, FR-007, FR-008, FR-009 | UC-001 | ENT-001, ENT-002, ENT-003, ENT-004, ENT-005, ENT-006 / API-001 | TC-001, TC-002, TC-003, TC-008, TC-009 |
| FR-010, FR-011, FR-012 | UC-002 | ENT-001, ENT-002, ENT-003, ENT-004, ENT-005, ENT-006 / API-002 | TC-004, TC-005, TC-006 |
| FR-013 | UC-003 | ENT-005..006 / API-003 | TC-007 |
| FR-014 | UC-002 | ENT-002 / API-002 | TC-005 |
| FR-015, FR-016, FR-017 | UC-001, UC-002, UC-003 | ENT-005, ENT-006 / API-001, API-002, API-003 | TC-010, TC-011, TC-012, TC-013, TC-014, TC-015 |
| FR-018, NFR-006 | UC-000 → UC-001 → UC-002 → UC-003 | PAGE-001..004 / API-000 → API-001 → API-002 → API-003 | TC-004, TC-007, TC-016 |
| FR-019, FR-020 | UC-000 | PAGE-002 / API-000 | TC-016 |
| NFR-001,004,005 | UC-002/003 | API-002/003 | TC-006, TC-007 |

TC mapping:

- TC-001: variasi header dan target audit.
- TC-002: kuantitas inkonsisten/pecahan diblokir.
- TC-003: kandidat berserial ambigu diblokir.
- TC-004: compare → apply → inline.
- TC-005: surplus membuat lokasi, katalog, dan Asset.
- TC-006: target nol tidak menghapus Asset dan apply idempotent.
- TC-007: compare ulang membuat batch anak dan hasil Inline.
- TC-008: baris unit tanpa serial diagregasi.
- TC-009: beberapa Asset tanpa serial dikonsolidasikan secara deterministik.
- TC-010: badan usaha wajib dan diteruskan ke Asset serta batch verifikasi.
- TC-011: badan usaha Asset yang berbeda menjadi Gap dan hanya berubah setelah apply eksplisit.
- TC-012: compare ulang batch lama mewajibkan badan usaha master eksplisit.
- TC-013: marker CSN memakai master CSN exact.
- TC-014: override Gudang menang dan alias unknown diblokir.
- TC-015: apply memakai target per item dan menolak staging tanpa mapping.

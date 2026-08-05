# Test Cases — Audit Kendaraan

Status: Reviewed

| ID | UC | Case |
|---|---|---|
| TC-VA-001 | UC-VA-001 | Parser menormalkan plat dan menandai TERJUAL sebagai target_qty 0 |
| TC-VA-002 | UC-VA-001 | Compare double plat → keep + retire_duplicate Gap |
| TC-VA-003 | UC-VA-001 | Compare sold masih available → mark_sold Gap |
| TC-VA-004 | UC-VA-002 | Apply mark_sold menulis condition sold tanpa delete |
| TC-VA-005 | UC-VA-002 | Apply retire_duplicate set inventory_active false pada non-keep |
| TC-VA-006 | UC-VA-002 | Apply create_missing membuat Asset + Plat Nomor |
| TC-VA-007 | UC-VA-003 | Recompare setelah apply → Inline |
| TC-VA-008 | UC-VA-000 | Export menghasilkan sheet Monitoring Asset |
| TC-VA-009 | UC-VA-001 | Unknown ACC marker → Blocked (FR-VA-007) |
| TC-VA-010 | UC-VA-001 | Create flag off → plat missing Blocked (FR-VA-011) |
| TC-VA-011 | UC-VA-002 | Apply kedua ditolak / idempotent (FR-VA-008) |
| TC-VA-012 | UC-VA-001 | orphan_shelf_plates terhitung (FR-VA-013) |
| TC-VA-013 | UC-VA-002 | Location alias sync / create-on-apply (FR-VA-018) |
| TC-VA-014 | UC-VA-002 | Holder exact unik → recipient_id (FR-VA-018) |

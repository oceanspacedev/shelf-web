# API-002 — Apply Contract

Status: Reviewed

Use case: UC-002.

Interface: `AssetReconciliationService::apply(AssetReconciliation): AssetReconciliation`.

Authorization dilakukan di Resource melalui izin import Asset. Validasi kontrak: status compared/aligned, default batch dan seluruh target item Gap masih menunjuk master resmi, `blocked_rows=0`, dan `applied_at=null`. Seluruh side effect berjalan dalam satu transaksi. Asset menerima `business_entity_id` masing-masing item. Snapshot, statistik, timestamp, dan idempotensi tetap berlaku.

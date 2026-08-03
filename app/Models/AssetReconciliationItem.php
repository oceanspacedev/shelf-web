<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetReconciliationItem extends Model
{
    public const STATUS_INLINE = 'inline';

    public const STATUS_GAP = 'gap';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_APPLIED = 'applied';

    protected $fillable = [
        'asset_reconciliation_id',
        'source_row',
        'source_rows',
        'external_business_entity_code',
        'external_location_code',
        'external_item_code',
        'item_name',
        'serial_number',
        'system_qty',
        'physical_qty',
        'correction_qty',
        'target_qty',
        'shelf_qty',
        'gap_qty',
        'asset_location_id',
        'asset_catalog_item_id',
        'business_entity_id',
        'matched_asset_id',
        'candidate_asset_ids',
        'match_strategy',
        'comparison_status',
        'action',
        'message',
        'notes',
        'raw_payload',
        'before_snapshot',
        'after_snapshot',
        'applied_at',
    ];

    protected $casts = [
        'source_rows' => 'array',
        'candidate_asset_ids' => 'array',
        'raw_payload' => 'array',
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'applied_at' => 'datetime',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(AssetReconciliation::class, 'asset_reconciliation_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'asset_location_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(AssetCatalogItem::class, 'asset_catalog_item_id');
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    public function matchedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'matched_asset_id');
    }
}

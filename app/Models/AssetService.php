<?php

namespace App\Models;

use App\Enums\AssetServiceStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetService extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_number',
        'asset_id',
        'provider_type',
        'serviced_by_user_id',
        'vendor_id',
        'technician_name',
        'contact_number',
        'service_date',
        'completion_date',
        'issue_description',
        'action_taken',
        'before_service_photo',
        'after_service_photo',
        'receipt_document_path',
        'total_cost',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'service_date' => 'date',
        'completion_date' => 'date',
        'status' => AssetServiceStatus::class,
        'total_cost' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (AssetService $service) {
            if (empty($service->service_number)) {
                $service->service_number = static::generateServiceNumber();
            }
            if (empty($service->created_by) && auth()->check()) {
                $service->created_by = auth()->id();
            }
        });
    }

    public static function generateServiceNumber(): string
    {
        $prefix = 'SRV-' . Carbon::now()->format('Ym') . '-';
        $latest = static::where('service_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('service_number');

        $nextNumber = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function servicedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'serviced_by_user_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetServiceItem::class, 'asset_service_id');
    }

    public function getProviderLabelAttribute(): string
    {
        if ($this->provider_type === 'internal') {
            return $this->servicedByUser?->name ? "Internal ({$this->servicedByUser->name})" : 'Internal';
        }

        $vendorName = $this->vendor?->name ?? $this->technician_name ?? 'Vendor Luar';
        return "Eksternal ({$vendorName})";
    }

    public function recalculateTotalCost(): void
    {
        $itemsTotal = $this->items()->sum('subtotal');
        if ($itemsTotal > 0 || $this->items()->exists()) {
            $this->update(['total_cost' => $itemsTotal]);
        }
    }
}

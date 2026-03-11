<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PublicAssetRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'request_type',
        'requester_name',
        'email',
        'division',
        'approval_track',
        'placement',
        'item_name',
        'qty',
        'attachment_path',
        'attachment_original_name',
        'status',
        'admin_notes',
        'user_id',
        'asset_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'status' => RequestStatus::class,
    ];

    protected static function booted(): void
    {
        static::forceDeleted(function (self $publicAssetRequest): void {
            if ($publicAssetRequest->attachment_path) {
                Storage::disk('public')->delete($publicAssetRequest->attachment_path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class)->orderBy('level');
    }

    /**
     * Get the current (active) approval step.
     */
    public function currentApproval(): ?RequestApproval
    {
        return $this->approvals()
            ->where('status', ApprovalStatus::Pending)
            ->orderBy('level')
            ->first();
    }

    /**
     * Calculate the overall approval status based on all approval steps.
     */
    public function computeOverallStatus(): RequestStatus
    {
        $approvals = $this->approvals;

        if ($approvals->isEmpty()) {
            return $this->status;
        }

        // If any is rejected → rejected
        if ($approvals->contains('status', ApprovalStatus::Rejected)) {
            return RequestStatus::Rejected;
        }

        // If all approved → approved
        if ($approvals->every(fn ($a) => $a->status === ApprovalStatus::Approved)) {
            return RequestStatus::Approved;
        }

        // Otherwise still pending
        return RequestStatus::Pending;
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }

    public function getAttachmentLabelAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return $this->attachment_original_name ?: basename($this->attachment_path);
    }
}

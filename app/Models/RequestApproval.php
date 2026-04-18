<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_asset_request_id',
        'approval_level_id',
        'token',
        'level',
        'approver_name',
        'approver_email',
        'status',
        'notes',
        'responded_at',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'responded_at' => 'datetime',
    ];

    public function publicAssetRequest(): BelongsTo
    {
        return $this->belongsTo(PublicAssetRequest::class);
    }

    public function approvalLevel(): BelongsTo
    {
        return $this->belongsTo(ApprovalLevel::class);
    }
}

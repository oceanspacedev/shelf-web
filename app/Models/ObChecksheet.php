<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObChecksheet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'user_id',
        'room',
        'status',
        'duration_minutes',
        'cleaned_at',
        'started_at',
        'finished_at',
        'before_photo',
        'after_photo',
        'notes',
    ];

    protected $casts = [
        'cleaned_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' || filled($this->after_photo);
    }

    public function markAsCompleted(string $afterPhoto, ?string $notes = null): void
    {
        $finishTime = now();
        $this->after_photo = $afterPhoto;
        $this->finished_at = $finishTime;
        $this->status = 'completed';

        if ($notes !== null) {
            $this->notes = $notes;
        }

        if ($this->started_at) {
            $this->duration_minutes = max(0, $this->started_at->diffInMinutes($finishTime));
        }

        $this->save();
    }

    protected static function booted(): void
    {
        static::creating(function (ObChecksheet $checksheet) {
            if (blank($checksheet->reference_number)) {
                $checksheet->reference_number = static::generateReferenceNumber();
            }

            if (blank($checksheet->user_id) && auth()->check()) {
                $checksheet->user_id = auth()->id();
            }

            if (blank($checksheet->started_at)) {
                $checksheet->started_at = now();
            }

            if (blank($checksheet->cleaned_at)) {
                $checksheet->cleaned_at = $checksheet->started_at;
            }

            if (blank($checksheet->status)) {
                $checksheet->status = filled($checksheet->after_photo) ? 'completed' : 'in_progress';
            }

            if ($checksheet->status === 'completed' && blank($checksheet->finished_at)) {
                $checksheet->finished_at = now();
            }

            if ($checksheet->started_at && $checksheet->finished_at && is_null($checksheet->duration_minutes)) {
                $checksheet->duration_minutes = max(0, $checksheet->started_at->diffInMinutes($checksheet->finished_at));
            }
        });

        static::saving(function (ObChecksheet $checksheet) {
            if ($checksheet->finished_at && $checksheet->started_at) {
                $checksheet->duration_minutes = max(0, $checksheet->started_at->diffInMinutes($checksheet->finished_at));
            }

            if (filled($checksheet->after_photo) && $checksheet->status !== 'completed') {
                $checksheet->status = 'completed';
                if (blank($checksheet->finished_at)) {
                    $checksheet->finished_at = now();
                }
            }
        });
    }

    public static function generateReferenceNumber(): string
    {
        $today = Carbon::now()->format('Ymd');
        $prefix = "OBC-{$today}-";

        $lastRecord = static::withTrashed()
            ->where('reference_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        $sequence = 1;
        if ($lastRecord && preg_match('/-(\d+)$/', $lastRecord->reference_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}

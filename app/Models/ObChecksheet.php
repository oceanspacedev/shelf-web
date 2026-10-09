<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObChecksheet extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const SOURCE_ASSIGNED = 'assigned';

    public const SOURCE_INITIATIVE = 'initiative';

    protected $fillable = [
        'reference_number',
        'user_id',
        'assigned_by',
        'source',
        'scheduled_date',
        'shift_label',
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
        'scheduled_date' => 'date',
        'cleaned_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Tugas milik user, ditambah tugas kolam bersama yang belum diambil siapa pun.
     *
     * @param  Builder<ObChecksheet>  $query
     * @return Builder<ObChecksheet>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhere(fn (Builder $query) => $query->unclaimed());
        });
    }

    /**
     * Tugas kolam bersama: ditugaskan atasan tanpa petugas, belum diambil.
     *
     * @param  Builder<ObChecksheet>  $query
     * @return Builder<ObChecksheet>
     */
    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->whereNull('user_id')->where('source', self::SOURCE_ASSIGNED);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Belum Dikerjakan',
            self::STATUS_IN_PROGRESS => 'Sedang Dikerjakan',
            self::STATUS_COMPLETED => 'Selesai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_ASSIGNED => 'Ditugaskan Atasan',
            self::SOURCE_INITIATIVE => 'Inisiatif OB',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status] ?? ($status ?? '-');
    }

    public static function sourceLabel(?string $source): string
    {
        return self::sourceOptions()[$source] ?? ($source ?? '-');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAssigned(): bool
    {
        return $this->source === self::SOURCE_ASSIGNED;
    }

    /**
     * Tugas kolam bersama yang belum punya petugas.
     */
    public function isUnclaimed(): bool
    {
        return blank($this->user_id);
    }

    /**
     * OB yang lebih dulu mengirim foto sebelum menjadi petugasnya. Update bersyarat
     * `user_id is null` menjamin hanya satu OB yang berhasil bila dua OB mengambil bersamaan.
     */
    public function claim(User $user): bool
    {
        if (filled($this->user_id)) {
            return $this->user_id === $user->id;
        }

        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereNull('user_id')
            ->where('status', self::STATUS_PENDING)
            ->update(['user_id' => $user->id]);

        if ($claimed === 0) {
            $this->refresh();

            return $this->user_id === $user->id;
        }

        $this->user_id = $user->id;
        $this->syncOriginalAttribute('user_id');
        $this->unsetRelation('user');

        return true;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED || filled($this->after_photo);
    }

    /**
     * Foto sebelum/sesudah tugas atasan sering diambil sekaligus, jadi selisih
     * waktunya bukan durasi kerja per ruangan; angkanya hanya tampil untuk inisiatif OB.
     */
    public function showsDuration(): bool
    {
        return ! $this->isAssigned();
    }

    /**
     * Tugas pending dimulai saat OB mengirim foto sebelum.
     */
    public function startWork(string $beforePhoto, ?string $notes = null): void
    {
        $this->before_photo = $beforePhoto;
        $this->started_at = now();
        $this->status = self::STATUS_IN_PROGRESS;

        if ($notes !== null) {
            $this->notes = $notes;
        }

        $this->save();
    }

    public function markAsCompleted(string $afterPhoto, ?string $notes = null): void
    {
        $finishTime = now();
        $this->after_photo = $afterPhoto;
        $this->finished_at = $finishTime;
        $this->status = self::STATUS_COMPLETED;

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

            // Tugas atasan tanpa petugas sengaja dibiarkan kosong (kolam bersama).
            if (blank($checksheet->user_id) && $checksheet->source !== self::SOURCE_ASSIGNED && auth()->check()) {
                $checksheet->user_id = auth()->id();
            }

            if (blank($checksheet->source)) {
                $checksheet->source = self::SOURCE_INITIATIVE;
            }

            if (blank($checksheet->status)) {
                $checksheet->status = match (true) {
                    $checksheet->source === self::SOURCE_ASSIGNED && blank($checksheet->before_photo) => self::STATUS_PENDING,
                    filled($checksheet->after_photo) => self::STATUS_COMPLETED,
                    default => self::STATUS_IN_PROGRESS,
                };
            }

            // Tugas pending baru punya waktu mulai saat OB mengirim foto sebelum.
            if (blank($checksheet->started_at) && $checksheet->status !== self::STATUS_PENDING) {
                $checksheet->started_at = now();
            }

            if (blank($checksheet->scheduled_date)) {
                $checksheet->scheduled_date = ($checksheet->started_at ?? now())->toDateString();
            }

            if (blank($checksheet->cleaned_at)) {
                $checksheet->cleaned_at = $checksheet->started_at ?? now();
            }

            if ($checksheet->status === self::STATUS_COMPLETED && blank($checksheet->finished_at)) {
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

            if (filled($checksheet->after_photo) && $checksheet->status !== self::STATUS_COMPLETED) {
                $checksheet->status = self::STATUS_COMPLETED;
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

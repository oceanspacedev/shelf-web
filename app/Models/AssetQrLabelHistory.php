<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;

class AssetQrLabelHistory extends Model
{
    public const ACTION_PRINT = 'print';

    public const ACTION_DOWNLOAD_PDF = 'download_pdf';

    protected $fillable = [
        'user_id',
        'action',
        'asset_ids',
        'asset_count',
        'asset_summary',
        'file_path',
        'file_disk',
        'file_name',
    ];

    protected function casts(): array
    {
        return [
            'asset_ids' => 'array',
            'asset_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_PRINT => 'Print Label QR',
            self::ACTION_DOWNLOAD_PDF => 'Download Label QR (PDF)',
            default => $this->action,
        };
    }

    public function assetsLabel(): string
    {
        if ($this->asset_count <= 1) {
            return $this->asset_summary ?: '—';
        }

        return $this->asset_count.' aset: '.($this->asset_summary ?: '—');
    }

    public function hasStoredFile(): bool
    {
        if (blank($this->file_path)) {
            return false;
        }

        // Disk yang tidak terjangkau atau tidak terkonfigurasi (mis. S3 kosong di lokal) dianggap file tidak ada.
        try {
            return Storage::disk($this->file_disk ?: 'local')->exists($this->file_path);
        } catch (FilesystemException|InvalidArgumentException $exception) {
            report($exception);

            return false;
        }
    }
}

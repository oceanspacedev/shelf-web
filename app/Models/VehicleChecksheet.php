<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class VehicleChecksheet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'reference_number',
        'pic',
        'license_plate',
        'location',
        'destination',
        'remarks',
        'start_km',
        'departure_time',
        'departure_photo',
        'departure_damage_report',
        'end_km',
        'return_time',
        'return_photo',
        'return_damage_report',
        'rental_duration',
        'distance_traveled',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'return_time' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    protected static function booted()
    {
        static::saving(function ($vehicleChecksheet) {
            $vehicleChecksheet->calculateRentalDetails();
        });

        static::deleting(function ($vehicleChecksheet) {
            $vehicleChecksheet->deleteRelatedFiles();
        });

        static::updating(function ($vehicleChecksheet) {
            $vehicleChecksheet->deleteOldFiles();
        });
    }

    protected function calculateRentalDetails(): void
    {
        // Periksa apakah kedua nilai start_km dan end_km valid
        if (isset($this->start_km, $this->end_km) && is_numeric($this->start_km) && is_numeric($this->end_km)) {
            $this->distance_traveled = max(0, $this->end_km - $this->start_km);
        } else {
            $this->distance_traveled = 0; // Set default jika data tidak valid
        }

        // Periksa apakah kedua nilai departure_time dan return_time valid
        if (isset($this->departure_time, $this->return_time)) {
            $departure = Carbon::parse($this->departure_time);
            $return = Carbon::parse($this->return_time);

            // Hitung durasi dalam menit
            $durationInMinutes = $departure->diffInMinutes($return);

            // Konversikan ke hari desimal
            $this->rental_duration = round($durationInMinutes / 1440, 5); // Menggunakan 5 desimal untuk presisi
        } else {
            $this->rental_duration = 0; // Set default jika data tidak valid
        }
    }

    protected function deleteRelatedFiles(): void
    {
        // Hapus file departure_photo dan departure_damage_report jika ada
        if ($this->departure_photo) {
            Storage::disk('public')->delete($this->departure_photo);
        }

        if ($this->departure_damage_report) {
            Storage::disk('public')->delete($this->departure_damage_report);
        }

        // Hapus file return_photo dan return_damage_report jika ada
        if ($this->return_photo) {
            Storage::disk('public')->delete($this->return_photo);
        }

        if ($this->return_damage_report) {
            Storage::disk('public')->delete($this->return_damage_report);
        }
    }

    protected function deleteOldFiles(): void
    {
        // Cek jika file baru diunggah, hapus file lama
        if ($this->isDirty('departure_photo') && $this->getOriginal('departure_photo')) {
            Storage::disk('public')->delete($this->getOriginal('departure_photo'));
        }

        if ($this->isDirty('departure_damage_report') && $this->getOriginal('departure_damage_report')) {
            Storage::disk('public')->delete($this->getOriginal('departure_damage_report'));
        }

        if ($this->isDirty('return_photo') && $this->getOriginal('return_photo')) {
            Storage::disk('public')->delete($this->getOriginal('return_photo'));
        }

        if ($this->isDirty('return_damage_report') && $this->getOriginal('return_damage_report')) {
            Storage::disk('public')->delete($this->getOriginal('return_damage_report'));
        }
    }
}

<?php

namespace App\Filament\Resources\ObChecksheetResource\Pages;

use App\Filament\Resources\ObChecksheetResource;
use App\Models\ObChecksheet;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateObChecksheet extends CreateRecord
{
    protected static string $resource = ObChecksheetResource::class;

    protected int $createdRoomCount = 0;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $count = $this->createdRoomCount;

        return $count === 1
            ? 'Pembersihan dimulai. Ambil Foto Sesudah untuk ruangan ini setelah selesai.'
            : "{$count} ruangan dimulai. Ambil Foto Sesudah satu per satu setelah selesai.";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $rooms = array_values($data['rooms'] ?? []);

        if ($rooms === []) {
            throw ValidationException::withMessages([
                'data.rooms' => 'Tambahkan minimal satu ruangan.',
            ]);
        }

        $created = null;

        foreach ($rooms as $room) {
            $photo = $room['before_photo'] ?? null;

            if (is_array($photo)) {
                $photo = collect($photo)->first(fn (mixed $value): bool => is_string($value) && $value !== '');
            }

            $created = ObChecksheet::create([
                'user_id' => auth()->id(),
                'room' => $room['room'] ?? null,
                'before_photo' => is_string($photo) ? $photo : null,
                'notes' => filled($room['notes'] ?? null) ? $room['notes'] : null,
            ]);
        }

        $this->createdRoomCount = count($rooms);

        return $created;
    }
}

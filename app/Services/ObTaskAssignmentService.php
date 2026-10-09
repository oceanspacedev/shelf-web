<?php

namespace App\Services;

use App\Models\ObChecksheet;
use App\Models\ObTaskTemplate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ObTaskAssignmentService
{
    /**
     * Buat satu tugas pending per ruangan template. Tanpa OB, tugas masuk kolam bersama.
     *
     * @return array{created: int, skipped: int}
     */
    public function assignTemplate(ObTaskTemplate $template, ?User $officeBoy, CarbonInterface $date, User $assigner): array
    {
        return $this->assignRooms(
            $template->items()->pluck('room')->all(),
            $officeBoy,
            $date,
            $assigner,
            $template->shift_label,
        );
    }

    /**
     * Ruangan yang sudah ditugaskan pada tanggal yang sama dilewati, supaya menekan tombol
     * dua kali tidak menggandakan tugas. Untuk satu OB yang dicek hanya miliknya; untuk
     * kolam bersama dicek semua tugas atasan di tanggal itu, termasuk yang sudah diambil.
     *
     * @param  array<int, string|null>  $rooms
     * @return array{created: int, skipped: int}
     */
    public function assignRooms(array $rooms, ?User $officeBoy, CarbonInterface $date, User $assigner, ?string $shiftLabel = null): array
    {
        $rooms = collect($rooms)
            ->map(fn (mixed $room): string => trim((string) $room))
            ->filter()
            ->unique(fn (string $room): string => mb_strtolower($room))
            ->values();

        return DB::transaction(function () use ($rooms, $officeBoy, $date, $assigner, $shiftLabel): array {
            $existing = ObChecksheet::query()
                ->when($officeBoy !== null, fn ($query) => $query->where('user_id', $officeBoy->id))
                ->where('source', ObChecksheet::SOURCE_ASSIGNED)
                ->whereDate('scheduled_date', $date->toDateString())
                ->pluck('room')
                ->map(fn (string $room): string => mb_strtolower(trim($room)))
                ->all();

            $created = 0;
            $skipped = 0;

            foreach ($rooms as $room) {
                if (in_array(mb_strtolower($room), $existing, true)) {
                    $skipped++;

                    continue;
                }

                ObChecksheet::create([
                    'user_id' => $officeBoy?->id,
                    'assigned_by' => $assigner->id,
                    'source' => ObChecksheet::SOURCE_ASSIGNED,
                    'status' => ObChecksheet::STATUS_PENDING,
                    'scheduled_date' => $date->toDateString(),
                    'shift_label' => $shiftLabel,
                    'room' => $room,
                ]);

                $created++;
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }
}

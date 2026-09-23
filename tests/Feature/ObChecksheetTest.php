<?php

namespace Tests\Feature;

use App\Filament\Resources\ObChecksheetResource;
use App\Models\ObChecksheet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ObChecksheetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ob_checksheet_can_be_created_with_auto_reference_number(): void
    {
        $user = User::factory()->create();

        $checksheet = ObChecksheet::create([
            'user_id' => $user->id,
            'room' => 'Lobby Utama',
            'cleaned_at' => now(),
            'before_photo' => 'ob-checksheets/before/lobby_before.jpg',
            'after_photo' => 'ob-checksheets/after/lobby_after.jpg',
            'notes' => 'Lobby bersih dan rapi',
        ]);

        $this->assertNotNull($checksheet->reference_number);
        $this->assertStringStartsWith('OBC-', $checksheet->reference_number);
        $this->assertSame('Lobby Utama', $checksheet->room);
        $this->assertSame($user->id, $checksheet->user->id);
    }

    public function test_reference_number_increments_for_multiple_records(): void
    {
        $user = User::factory()->create();

        $first = ObChecksheet::create([
            'user_id' => $user->id,
            'room' => 'Toilet Lantai 1',
            'cleaned_at' => now(),
            'before_photo' => 'before1.jpg',
            'after_photo' => 'after1.jpg',
        ]);

        $second = ObChecksheet::create([
            'user_id' => $user->id,
            'room' => 'Toilet Lantai 2',
            'cleaned_at' => now(),
            'before_photo' => 'before2.jpg',
            'after_photo' => 'after2.jpg',
        ]);

        $this->assertNotSame($first->reference_number, $second->reference_number);
    }

    public function test_rooms_query_returns_distinct_room_names(): void
    {
        $user = User::factory()->create();

        $uniqueTag = uniqid('room_');
        $room1 = $uniqueTag . '_A';
        $room2 = $uniqueTag . '_B';

        ObChecksheet::create([
            'user_id' => $user->id,
            'room' => $room1,
            'cleaned_at' => now(),
            'before_photo' => 'b1.jpg',
            'after_photo' => 'a1.jpg',
        ]);

        ObChecksheet::create([
            'user_id' => $user->id,
            'room' => $room1,
            'cleaned_at' => now(),
            'before_photo' => 'b2.jpg',
            'after_photo' => 'a2.jpg',
        ]);

        ObChecksheet::create([
            'user_id' => $user->id,
            'room' => $room2,
            'cleaned_at' => now(),
            'before_photo' => 'b3.jpg',
            'after_photo' => 'a3.jpg',
        ]);

        $rooms = ObChecksheet::query()
            ->where('room', 'like', "{$uniqueTag}%")
            ->distinct()
            ->pluck('room')
            ->toArray();

        $this->assertCount(2, $rooms);
        $this->assertContains($room1, $rooms);
        $this->assertContains($room2, $rooms);
    }

    public function test_office_boy_role_has_proper_permissions(): void
    {
        $role = Role::where('name', 'office_boy')->first();
        $this->assertNotNull($role);

        $this->assertTrue($role->hasPermissionTo('view_any_ob::checksheet'));
        $this->assertTrue($role->hasPermissionTo('view_ob::checksheet'));
        $this->assertTrue($role->hasPermissionTo('create_ob::checksheet'));
        $this->assertFalse($role->hasPermissionTo('delete_ob::checksheet'));
    }

    public function test_super_admin_has_all_ob_checksheet_permissions(): void
    {
        $role = Role::where('name', 'super_admin')->first();
        $this->assertNotNull($role);

        $this->assertTrue($role->hasPermissionTo('view_any_ob::checksheet'));
        $this->assertTrue($role->hasPermissionTo('create_ob::checksheet'));
        $this->assertTrue($role->hasPermissionTo('update_ob::checksheet'));
        $this->assertTrue($role->hasPermissionTo('delete_ob::checksheet'));
        $this->assertTrue($role->hasPermissionTo('delete_any_ob::checksheet'));
    }

    public function test_policy_authorizes_user_based_on_permissions(): void
    {
        $obUser = User::factory()->create();
        $obRole = Role::where('name', 'office_boy')->first();
        $obUser->assignRole($obRole);

        $checksheet = ObChecksheet::create([
            'user_id' => $obUser->id,
            'room' => 'Pantry',
            'cleaned_at' => now(),
            'before_photo' => 'b.jpg',
            'after_photo' => 'a.jpg',
        ]);

        $this->assertTrue($obUser->can('viewAny', ObChecksheet::class));
        $this->assertTrue($obUser->can('view', $checksheet));
        $this->assertTrue($obUser->can('create', ObChecksheet::class));
        $this->assertFalse($obUser->can('delete', $checksheet));
    }

    public function test_checksheet_starts_as_in_progress_without_after_photo(): void
    {
        $user = User::factory()->create();

        $checksheet = ObChecksheet::create([
            'user_id' => $user->id,
            'room' => 'Ruang Meeting 2',
            'before_photo' => 'ob-checksheets/before/meeting.jpg',
        ]);

        $this->assertSame('in_progress', $checksheet->status);
        $this->assertNotNull($checksheet->started_at);
        $this->assertNull($checksheet->after_photo);
        $this->assertNull($checksheet->finished_at);
        $this->assertNull($checksheet->duration_minutes);
        $this->assertFalse($checksheet->isCompleted());
    }

    public function test_mark_as_completed_records_timestamp_and_duration(): void
    {
        $user = User::factory()->create();

        $startedAt = now()->subMinutes(15);
        $checksheet = ObChecksheet::create([
            'user_id' => $user->id,
            'room' => 'Toilet Lantai 3',
            'started_at' => $startedAt,
            'before_photo' => 'ob-checksheets/before/toilet3.jpg',
        ]);

        $checksheet->markAsCompleted('ob-checksheets/after/toilet3.jpg', 'Kaca dan lantai sudah disikat');

        $this->assertSame('completed', $checksheet->status);
        $this->assertSame('ob-checksheets/after/toilet3.jpg', $checksheet->after_photo);
        $this->assertSame('Kaca dan lantai sudah disikat', $checksheet->notes);
        $this->assertNotNull($checksheet->finished_at);
        $this->assertGreaterThanOrEqual(14, $checksheet->duration_minutes);
        $this->assertTrue($checksheet->isCompleted());
    }

    public function test_ob_user_only_sees_own_checksheets_in_resource_query(): void
    {
        $obRole = Role::where('name', 'office_boy')->first();

        $ob1 = User::factory()->create();
        $ob1->assignRole($obRole);

        $ob2 = User::factory()->create();
        $ob2->assignRole($obRole);

        $record1 = ObChecksheet::create([
            'user_id' => $ob1->id,
            'room' => 'Ruang Server OB 1',
            'before_photo' => 'ob-checksheets/before/server1.jpg',
        ]);

        $record2 = ObChecksheet::create([
            'user_id' => $ob2->id,
            'room' => 'Ruang Server OB 2',
            'before_photo' => 'ob-checksheets/before/server2.jpg',
        ]);

        // When authenticated as OB 1
        $this->actingAs($ob1);
        $ob1Ids = ObChecksheetResource::getEloquentQuery()->pluck('id')->toArray();

        $this->assertContains($record1->id, $ob1Ids);
        $this->assertNotContains($record2->id, $ob1Ids);

        // OB 1 cannot view record 2 via policy
        $this->assertFalse($ob1->can('view', $record2));
        $this->assertTrue($ob1->can('view', $record1));

        // When authenticated as super admin
        $adminRole = Role::where('name', 'super_admin')->first();
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $this->actingAs($admin);
        $adminIds = ObChecksheetResource::getEloquentQuery()->pluck('id')->toArray();

        $this->assertContains($record1->id, $adminIds);
        $this->assertContains($record2->id, $adminIds);
    }

    public function test_new_ob_user_has_empty_room_history_suggestions(): void
    {
        $obRole = Role::where('name', 'office_boy')->first();

        // Existing checksheet from another user
        $otherUser = User::factory()->create();
        ObChecksheet::create([
            'user_id' => $otherUser->id,
            'room' => 'Ruang Direktur Eksisting',
            'before_photo' => 'b.jpg',
        ]);

        // Brand new OB user
        $newOb = User::factory()->create();
        $newOb->assignRole($obRole);

        $this->actingAs($newOb);

        // Check the datalist logic directly as executed by form
        $user = auth()->user();
        $query = ObChecksheet::query()
            ->whereNotNull('room')
            ->where('room', '!=', '');

        $suggestions = $query->where('user_id', $user->id)
            ->distinct()
            ->pluck('room')
            ->toArray();

        $this->assertEmpty($suggestions);
    }

    public function test_ob_checksheet_exporter_has_expected_columns(): void
    {
        $columns = \App\Filament\Exports\ObChecksheetExporter::getColumns();

        $columnNames = array_map(fn ($col) => $col->getName(), $columns);

        $this->assertContains('reference_number', $columnNames);
        $this->assertContains('user.name', $columnNames);
        $this->assertContains('room', $columnNames);
        $this->assertContains('status', $columnNames);
        $this->assertContains('started_at', $columnNames);
        $this->assertContains('finished_at', $columnNames);
        $this->assertContains('duration_minutes', $columnNames);
        $this->assertContains('before_photo', $columnNames);
        $this->assertContains('after_photo', $columnNames);
        $this->assertContains('notes', $columnNames);
        $this->assertContains('created_at', $columnNames);

        $exporter = new \App\Filament\Exports\ObChecksheetExporter(
            new \Filament\Actions\Exports\Models\Export,
            [],
            []
        );

        $this->assertSame('exports', $exporter->getJobQueue());
        $this->assertSame([], $exporter->getJobMiddleware());
    }

    public function test_ob_checksheet_exporter_formats_record_properly(): void
    {
        $user = User::factory()->create(['name' => 'Budi Petugas']);

        $checksheet = ObChecksheet::create([
            'user_id' => $user->id,
            'room' => 'Lobby Gedung A',
            'started_at' => now()->subMinutes(25),
            'before_photo' => 'ob-checksheets/before/test_before.jpg',
        ]);
        $checksheet->markAsCompleted('ob-checksheets/after/test_after.jpg', 'Kaca jendela sudah bersih');

        $exporter = new \App\Filament\Exports\ObChecksheetExporter(
            new \Filament\Actions\Exports\Models\Export([
                'file_disk' => 'local',
                'exporter' => \App\Filament\Exports\ObChecksheetExporter::class,
                'total_rows' => 1,
                'user_id' => $user->id,
            ]),
            ['reference_number' => 'No. Referensi', 'user.name' => 'Petugas OB', 'room' => 'Nama Ruangan', 'status' => 'Status', 'duration_minutes' => 'Durasi', 'notes' => 'Catatan'],
            []
        );

        $row = $exporter($checksheet);

        $this->assertContains($checksheet->reference_number, $row);
        $this->assertContains('Budi Petugas', $row);
        $this->assertContains('Lobby Gedung A', $row);
        $this->assertContains('Selesai', $row);
        $this->assertContains('Kaca jendela sudah bersih', $row);
    }

    public function test_ob_checksheet_exporter_query_scoping(): void
    {
        $ob1 = User::factory()->create();
        $ob2 = User::factory()->create();

        $checksheet1 = ObChecksheet::create([
            'user_id' => $ob1->id,
            'room' => 'Ruang 1',
            'before_photo' => 'b1.jpg',
        ]);

        $checksheet2 = ObChecksheet::create([
            'user_id' => $ob2->id,
            'room' => 'Ruang 2',
            'before_photo' => 'b2.jpg',
        ]);

        // When acting as OB 1
        $this->actingAs($ob1);
        $scopedQuery = \App\Filament\Exports\ObChecksheetExporter::modifyQuery(ObChecksheet::query());
        $results = $scopedQuery->pluck('id')->toArray();

        $this->assertContains($checksheet1->id, $results);
        $this->assertNotContains($checksheet2->id, $results);

        // When acting as Super Admin
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('name', 'super_admin')->first());
        $this->actingAs($admin);

        $adminQuery = \App\Filament\Exports\ObChecksheetExporter::modifyQuery(ObChecksheet::query());
        $adminResults = $adminQuery->pluck('id')->toArray();

        $this->assertContains($checksheet1->id, $adminResults);
        $this->assertContains($checksheet2->id, $adminResults);
    }

    public function test_ob_checksheet_export_permission_granted(): void
    {
        $superAdmin = Role::where('name', 'super_admin')->first();
        $this->assertTrue($superAdmin->hasPermissionTo('export_ob::checksheet'));

        $officeBoy = Role::where('name', 'office_boy')->first();
        $this->assertTrue($officeBoy->hasPermissionTo('export_ob::checksheet'));
    }
}

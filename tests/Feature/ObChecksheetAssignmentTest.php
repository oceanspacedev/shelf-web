<?php

namespace Tests\Feature;

use App\Filament\Exports\ObChecksheetExporter;
use App\Filament\Resources\ObChecksheetResource;
use App\Filament\Resources\ObChecksheetResource\Pages\ListObChecksheets;
use App\Filament\Resources\ObChecksheetResource\Pages\ViewObChecksheet;
use App\Filament\Resources\ObTaskTemplateResource\Pages\ManageObTaskTemplates;
use App\Models\ObChecksheet;
use App\Models\ObTaskTemplate;
use App\Models\User;
use App\Services\ObTaskAssignmentService;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ObChecksheetAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    private const OFFICE_BOY_PERMISSIONS = [
        'view_any_ob::checksheet',
        'view_ob::checksheet',
        'create_ob::checksheet',
    ];

    private const SUPERVISOR_PERMISSIONS = [
        'view_any_ob::checksheet',
        'view_ob::checksheet',
        'create_ob::checksheet',
        'update_ob::checksheet',
        'delete_ob::checksheet',
        'view_all_ob::checksheet',
        'update_all_ob::checksheet',
        'assign_ob::checksheet',
        'export_ob::checksheet',
        'view_any_ob::task::template',
        'view_ob::task::template',
        'create_ob::task::template',
        'update_ob::task::template',
        'delete_ob::task::template',
        'delete_any_ob::task::template',
    ];

    public function test_assigned_task_starts_pending_without_start_time_or_photo(): void
    {
        [$supervisor, $officeBoy] = [$this->supervisor(), $this->officeBoy()];

        $task = ObChecksheet::create([
            'user_id' => $officeBoy->id,
            'assigned_by' => $supervisor->id,
            'source' => ObChecksheet::SOURCE_ASSIGNED,
            'room' => 'Ruang Audit',
            'shift_label' => 'Shift Pagi',
        ]);

        $this->assertSame(ObChecksheet::STATUS_PENDING, $task->status);
        $this->assertNull($task->started_at);
        $this->assertNull($task->before_photo);
        $this->assertNotNull($task->scheduled_date);
        $this->assertTrue($task->isPending());
        $this->assertFalse($task->showsDuration());
    }

    public function test_initiative_task_keeps_the_existing_flow(): void
    {
        $officeBoy = $this->officeBoy();

        $task = ObChecksheet::create([
            'user_id' => $officeBoy->id,
            'room' => 'Lobby',
            'before_photo' => 'ob-checksheets/before/lobby.jpg',
        ]);

        $this->assertSame(ObChecksheet::SOURCE_INITIATIVE, $task->source);
        $this->assertSame(ObChecksheet::STATUS_IN_PROGRESS, $task->status);
        $this->assertNotNull($task->started_at);
        $this->assertTrue($task->scheduled_date->isToday());
        $this->assertTrue($task->showsDuration());
    }

    public function test_start_work_marks_a_pending_task_in_progress(): void
    {
        $task = $this->pendingTask($this->officeBoy(), 'Ruang Data');

        $task->startWork('ob-checksheets/before/data.jpg', 'Lantai kotor');

        $task->refresh();
        $this->assertSame(ObChecksheet::STATUS_IN_PROGRESS, $task->status);
        $this->assertSame('ob-checksheets/before/data.jpg', $task->before_photo);
        $this->assertSame('Lantai kotor', $task->notes);
        $this->assertNotNull($task->started_at);
    }

    public function test_service_assigns_one_pending_task_per_template_room_and_skips_repeats(): void
    {
        [$supervisor, $officeBoy] = [$this->supervisor(), $this->officeBoy()];
        $template = $this->template('Shift Pagi Petra', ['Ruang Chief', 'Ruang Audit', 'Aula']);
        $date = now()->addDay();

        $service = app(ObTaskAssignmentService::class);

        $first = $service->assignTemplate($template, $officeBoy, $date, $supervisor);
        $second = $service->assignTemplate($template, $officeBoy, $date, $supervisor);

        $this->assertSame(['created' => 3, 'skipped' => 0], $first);
        $this->assertSame(['created' => 0, 'skipped' => 3], $second);

        $tasks = ObChecksheet::query()->where('user_id', $officeBoy->id)->orderBy('id')->get();
        $this->assertSame(['Ruang Chief', 'Ruang Audit', 'Aula'], $tasks->pluck('room')->all());
        $this->assertTrue($tasks->every(fn (ObChecksheet $task): bool => $task->status === ObChecksheet::STATUS_PENDING
            && $task->source === ObChecksheet::SOURCE_ASSIGNED
            && $task->assigned_by === $supervisor->id
            && $task->shift_label === 'Shift Pagi Petra'
            && $task->scheduled_date->isSameDay($date)));
    }

    public function test_one_office_boy_can_receive_several_templates_on_the_same_day(): void
    {
        [$supervisor, $officeBoy] = [$this->supervisor(), $this->officeBoy()];
        $service = app(ObTaskAssignmentService::class);

        $service->assignTemplate($this->template('Pagi', ['Ruang A']), $officeBoy, now(), $supervisor);
        $service->assignTemplate($this->template('Middle', ['Ruang B']), $officeBoy, now(), $supervisor);

        $this->assertSame(2, ObChecksheet::query()->where('user_id', $officeBoy->id)->count());
    }

    public function test_assign_permission_is_separate_from_view_all(): void
    {
        $this->assertTrue($this->supervisor()->can('assign', ObChecksheet::class));
        $this->assertFalse($this->officeBoy()->can('assign', ObChecksheet::class));
        $this->assertFalse($this->userWithPermissions(['view_all_ob::checksheet'])->can('assign', ObChecksheet::class));
    }

    public function test_supervisor_assigns_from_template_through_the_list_page(): void
    {
        [$supervisor, $officeBoy] = [$this->supervisor(), $this->officeBoy()];
        $template = $this->template('Shift Middle Petra', ['Ruang IT', 'Mushola']);

        $this->actAs($supervisor);

        Livewire::test(ListObChecksheets::class)
            ->callAction('assignFromTemplate', [
                'template_id' => $template->id,
                'user_id' => $officeBoy->id,
                'date' => today()->toDateString(),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('2 tugas dibuat');

        $this->assertSame(
            ['Ruang IT', 'Mushola'],
            ObChecksheet::query()->where('user_id', $officeBoy->id)->orderBy('id')->pluck('room')->all(),
        );
    }

    public function test_supervisor_cannot_assign_to_a_user_who_is_not_an_office_boy(): void
    {
        $supervisor = $this->supervisor();
        $outsider = User::factory()->create();

        $this->actAs($supervisor);

        $this->callWithRepeater(
            Livewire::test(ListObChecksheets::class),
            'assignManual',
            'rooms',
            ['user_id' => $outsider->id, 'date' => today()->toDateString()],
            [['room' => 'Ruang Rahasia']],
        );

        $this->assertFalse(ObChecksheet::query()->where('room', 'Ruang Rahasia')->exists());
    }

    public function test_supervisor_adds_a_one_off_task_manually(): void
    {
        [$supervisor, $officeBoy] = [$this->supervisor(), $this->officeBoy()];

        $this->actAs($supervisor);

        $this->callWithRepeater(
            Livewire::test(ListObChecksheets::class),
            'assignManual',
            'rooms',
            ['user_id' => $officeBoy->id, 'date' => today()->toDateString(), 'shift_label' => 'Shift Pagi'],
            [['room' => 'Ruang Tamu Direksi'], ['room' => 'Teras']],
        )->assertHasNoActionErrors();

        $tasks = ObChecksheet::query()->where('user_id', $officeBoy->id)->orderBy('id')->get();
        $this->assertSame(['Ruang Tamu Direksi', 'Teras'], $tasks->pluck('room')->all());
        $this->assertSame('Shift Pagi', $tasks->first()->shift_label);
    }

    public function test_office_boy_does_not_see_assignment_actions(): void
    {
        $this->actAs($this->officeBoy());

        Livewire::test(ListObChecksheets::class)
            ->assertActionHidden('assignFromTemplate')
            ->assertActionHidden('assignManual');
    }

    public function test_start_all_only_starts_rooms_with_a_before_photo(): void
    {
        $officeBoy = $this->officeBoy();
        $photographed = $this->pendingTask($officeBoy, 'Ruang Audit');
        $skipped = $this->pendingTask($officeBoy, 'Ruang Purchasing');

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->assertActionVisible('startAll')
            ->callAction('startAll', [
                'tasks' => [
                    ['id' => $photographed->id, 'room' => 'Ruang Audit', 'photo' => 'ob-checksheets/before/audit.jpg', 'notes' => 'Berdebu'],
                    ['id' => $skipped->id, 'room' => 'Ruang Purchasing', 'photo' => null, 'notes' => null],
                ],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('1 ruangan dimulai');

        $photographed->refresh();
        $skipped->refresh();

        $this->assertSame(ObChecksheet::STATUS_IN_PROGRESS, $photographed->status);
        $this->assertSame('ob-checksheets/before/audit.jpg', $photographed->before_photo);
        $this->assertSame('Berdebu', $photographed->notes);
        $this->assertNotNull($photographed->started_at);

        $this->assertSame(ObChecksheet::STATUS_PENDING, $skipped->status);
        $this->assertNull($skipped->started_at);
        $this->assertNull($skipped->before_photo);
    }

    public function test_start_all_requires_at_least_one_photo(): void
    {
        $officeBoy = $this->officeBoy();
        $task = $this->pendingTask($officeBoy, 'Ruang Audit');

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->callAction('startAll', [
                'tasks' => [['id' => $task->id, 'room' => 'Ruang Audit', 'photo' => null, 'notes' => null]],
            ])
            ->assertNotified('Isi foto minimal satu ruangan')
            ->assertActionHalted('startAll');

        $this->assertSame(ObChecksheet::STATUS_PENDING, $task->refresh()->status);
    }

    public function test_start_all_ignores_tasks_that_belong_to_someone_else(): void
    {
        $officeBoy = $this->officeBoy();
        $someoneElse = $this->officeBoy();
        $own = $this->pendingTask($officeBoy, 'Ruang Audit');
        $foreign = $this->pendingTask($someoneElse, 'Ruang Chief');

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->callAction('startAll', [
                'tasks' => [
                    ['id' => $own->id, 'room' => 'Ruang Audit', 'photo' => 'ob-checksheets/before/a.jpg', 'notes' => null],
                    ['id' => $foreign->id, 'room' => 'Ruang Chief', 'photo' => 'ob-checksheets/before/b.jpg', 'notes' => null],
                ],
            ])
            ->assertNotified('1 ruangan dimulai');

        $this->assertSame(ObChecksheet::STATUS_IN_PROGRESS, $own->refresh()->status);
        $this->assertSame(ObChecksheet::STATUS_PENDING, $foreign->refresh()->status);
        $this->assertNull($foreign->before_photo);
    }

    public function test_start_all_only_lists_tasks_scheduled_for_today(): void
    {
        $officeBoy = $this->officeBoy();
        $this->pendingTask($officeBoy, 'Ruang Besok', now()->addDay());

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)->assertActionHidden('startAll');
    }

    public function test_finish_all_only_completes_rooms_with_an_after_photo(): void
    {
        $officeBoy = $this->officeBoy();
        $done = $this->inProgressTask($officeBoy, 'Ruang Audit');
        $notYet = $this->inProgressTask($officeBoy, 'Aula');

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->assertActionVisible('finishAll')
            ->callAction('finishAll', [
                'tasks' => [
                    ['id' => $done->id, 'room' => 'Ruang Audit', 'photo' => 'ob-checksheets/after/audit.jpg', 'notes' => null],
                    ['id' => $notYet->id, 'room' => 'Aula', 'photo' => null, 'notes' => null],
                ],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('1 ruangan selesai');

        $done->refresh();
        $notYet->refresh();

        $this->assertSame(ObChecksheet::STATUS_COMPLETED, $done->status);
        $this->assertSame('ob-checksheets/after/audit.jpg', $done->after_photo);
        $this->assertNotNull($done->finished_at);

        $this->assertSame(ObChecksheet::STATUS_IN_PROGRESS, $notYet->status);
        $this->assertNull($notYet->after_photo);
    }

    public function test_finish_all_does_not_list_pending_rooms(): void
    {
        $officeBoy = $this->officeBoy();
        $this->pendingTask($officeBoy, 'Ruang Audit');

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)->assertActionHidden('finishAll');
    }

    public function test_row_actions_follow_the_task_status(): void
    {
        $officeBoy = $this->officeBoy();
        $pending = $this->pendingTask($officeBoy, 'Ruang Audit');
        $running = $this->inProgressTask($officeBoy, 'Aula');

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->assertTableActionVisible('start', $pending)
            ->assertTableActionHidden('complete', $pending)
            ->assertTableActionHidden('start', $running)
            ->assertTableActionVisible('complete', $running);
    }

    public function test_row_start_action_starts_a_straggler_task(): void
    {
        $officeBoy = $this->officeBoy();
        $task = $this->pendingTask($officeBoy, 'Ruang Audit', now()->subDay());

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->callTableAction('start', $task, ['before_photo' => 'ob-checksheets/before/late.jpg'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ObChecksheet::STATUS_IN_PROGRESS, $task->refresh()->status);
    }

    public function test_table_filters_by_date_range(): void
    {
        $officeBoy = $this->officeBoy();
        $yesterday = $this->pendingTask($officeBoy, 'Ruang Kemarin', now()->subDay());
        $today = $this->pendingTask($officeBoy, 'Ruang Hari Ini', now());
        $tomorrow = $this->pendingTask($officeBoy, 'Ruang Besok', now()->addDay());

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->assertCanSeeTableRecords([$yesterday, $today, $tomorrow])
            ->filterTable('scheduled_date', ['from' => today()->toDateString(), 'until' => today()->toDateString()])
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$yesterday, $tomorrow]);
    }

    public function test_supervisor_filters_by_person_and_source(): void
    {
        $supervisor = $this->supervisor();
        [$budi, $sari] = [$this->officeBoy(), $this->officeBoy()];
        $budiTask = $this->pendingTask($budi, 'Ruang Budi');
        $sariTask = $this->pendingTask($sari, 'Ruang Sari');
        $initiative = ObChecksheet::create(['user_id' => $sari->id, 'room' => 'Ruang Inisiatif', 'before_photo' => 'ob/x.jpg']);

        $this->actAs($supervisor);

        Livewire::test(ListObChecksheets::class)
            ->filterTable('user_id', $budi->id)
            ->assertCanSeeTableRecords([$budiTask])
            ->assertCanNotSeeTableRecords([$sariTask, $initiative])
            ->resetTableFilters()
            ->filterTable('source', ObChecksheet::SOURCE_INITIATIVE)
            ->assertCanSeeTableRecords([$initiative])
            ->assertCanNotSeeTableRecords([$budiTask, $sariTask]);
    }

    public function test_office_boy_only_sees_their_own_assigned_tasks(): void
    {
        [$budi, $sari] = [$this->officeBoy(), $this->officeBoy()];
        $budiTask = $this->pendingTask($budi, 'Ruang Budi');
        $sariTask = $this->pendingTask($sari, 'Ruang Sari');

        $this->actAs($budi);

        Livewire::test(ListObChecksheets::class)
            ->assertCanSeeTableRecords([$budiTask])
            ->assertCanNotSeeTableRecords([$sariTask]);
    }

    public function test_supervisor_reassigns_unfinished_tasks_but_not_completed_ones(): void
    {
        $supervisor = $this->supervisor();
        [$sick, $replacement] = [$this->officeBoy(), $this->officeBoy()];
        $pending = $this->pendingTask($sick, 'Ruang Audit');
        $running = $this->inProgressTask($sick, 'Aula');
        $done = $this->inProgressTask($sick, 'Toilet Lt.2');
        $done->markAsCompleted('ob-checksheets/after/toilet.jpg');

        $this->actAs($supervisor);

        Livewire::test(ListObChecksheets::class)
            ->callTableBulkAction('reassign', [$pending, $running, $done], ['user_id' => $replacement->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame($replacement->id, $pending->refresh()->user_id);
        $this->assertSame($replacement->id, $running->refresh()->user_id);
        $this->assertSame($sick->id, $done->refresh()->user_id);
    }

    public function test_office_boy_cannot_reassign_tasks(): void
    {
        $officeBoy = $this->officeBoy();
        $task = $this->pendingTask($officeBoy, 'Ruang Audit');

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->assertTableBulkActionHidden('reassign');

        $this->assertSame($officeBoy->id, $task->refresh()->user_id);
    }

    public function test_card_hides_duration_for_assigned_tasks_but_keeps_it_for_initiative(): void
    {
        $officeBoy = $this->officeBoy();
        $assigned = $this->inProgressTask($officeBoy, 'Ruang Audit');
        $assigned->markAsCompleted('ob-checksheets/after/a.jpg');
        $initiative = ObChecksheet::create([
            'user_id' => $officeBoy->id,
            'room' => 'Lobby',
            'started_at' => now()->subMinutes(20),
            'before_photo' => 'ob/lobby.jpg',
        ]);
        $initiative->markAsCompleted('ob-checksheets/after/lobby.jpg');

        $this->actAs($officeBoy);

        $assigned->refresh();
        $initiative->refresh();

        // Kartu menampilkan jam di bawah nama ruangan: tugas atasan tanpa durasi, inisiatif dengan durasi.
        Livewire::test(ListObChecksheets::class)
            ->assertTableColumnHasDescription('room', $assigned->started_at->format('d M H:i').' → '.$assigned->finished_at->format('H:i'), $assigned)
            ->assertTableColumnHasDescription('room', $initiative->started_at->format('d M H:i')." ({$initiative->duration_minutes} mnt)", $initiative);

        $exporter = new ObChecksheetExporter(
            new Export(['file_disk' => 'local', 'exporter' => ObChecksheetExporter::class, 'total_rows' => 2, 'user_id' => $officeBoy->id]),
            ['duration_minutes' => 'Durasi (Menit)', 'source' => 'Jenis'],
            [],
        );

        $this->assertContains('-', $exporter($assigned));
        $this->assertContains('Ditugaskan Atasan', $exporter($assigned));
        $this->assertNotContains('-', $exporter($initiative));
        $this->assertContains('Inisiatif OB', $exporter($initiative));
    }

    public function test_list_is_a_responsive_card_grid_without_photos(): void
    {
        $officeBoy = $this->officeBoy();
        $task = $this->inProgressTask($officeBoy, 'Ruang Audit');
        $task->markAsCompleted('ob-checksheets/after/audit.jpg');

        $this->actAs($officeBoy);

        $list = Livewire::test(ListObChecksheets::class);
        $table = $list->instance()->getTable();

        $this->assertSame(['md' => 2, 'xl' => 3], $table->getContentGrid());
        $this->assertNull($table->getColumn('photos'));
        $this->assertNull($table->getColumn('before_photo'));
        $this->assertNull($table->getColumn('after_photo'));

        $list->assertSeeHtml('fi-ta-stack')
            ->assertSee('Ruang Audit')
            ->assertDontSeeHtml('ob-checksheets/before/')
            ->assertDontSeeHtml('ob-checksheets/after/');
    }

    public function test_card_search_finds_tasks_by_room_petugas_and_reference_number(): void
    {
        $supervisor = $this->supervisor();
        $budi = $this->officeBoy();
        $budi->update(['name' => 'Budi Kartu']);
        $task = $this->pendingTask($budi, 'Ruang Kartu Unik');
        $other = $this->pendingTask($this->officeBoy(), 'Ruang Lain Sekali');

        $this->actAs($supervisor);

        Livewire::test(ListObChecksheets::class)
            ->searchTable('Kartu Unik')
            ->assertCanSeeTableRecords([$task])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('Budi Kartu')
            ->assertCanSeeTableRecords([$task])
            ->searchTable($task->reference_number)
            ->assertCanSeeTableRecords([$task])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_detail_photos_are_marked_for_the_lightbox(): void
    {
        $officeBoy = $this->officeBoy();
        $task = $this->inProgressTask($officeBoy, 'Ruang Audit');
        $task->markAsCompleted('ob-checksheets/after/audit.jpg');

        $this->actAs($officeBoy);

        Livewire::test(ViewObChecksheet::class, ['record' => $task->getKey()])
            ->assertSeeHtml('data-lightbox="true"');

        $this->assertArrayHasKey('data-lightbox', ObChecksheetResource::lightboxAttributes());
    }

    public function test_lightbox_is_rendered_only_on_ob_checksheet_pages(): void
    {
        $this->actAs($this->officeBoy());
        // Render hook panel baru terdaftar saat panel di-boot (biasanya oleh request pertama).
        Filament::getCurrentPanel()->boot();

        $onList = FilamentView::renderHook(PanelsRenderHook::BODY_END, scopes: ListObChecksheets::class)->toHtml();
        $onView = FilamentView::renderHook(PanelsRenderHook::BODY_END, scopes: ViewObChecksheet::class)->toHtml();
        $elsewhere = FilamentView::renderHook(PanelsRenderHook::BODY_END, scopes: ManageObTaskTemplates::class)->toHtml();

        $this->assertStringContainsString('img[data-lightbox]', $onList);
        $this->assertStringContainsString('img[data-lightbox]', $onView);
        $this->assertStringNotContainsString('img[data-lightbox]', $elsewhere);
    }

    public function test_supervisor_manages_templates(): void
    {
        $this->actAs($this->supervisor());

        $this->callWithRepeater(
            Livewire::test(ManageObTaskTemplates::class),
            'create',
            'items',
            ['name' => 'Shift Pagi Petra', 'shift_label' => 'Shift Pagi', 'is_active' => true],
            [['room' => 'Ruang Chief'], ['room' => 'Ruang Audit']],
        )->assertHasNoActionErrors();

        $template = ObTaskTemplate::query()->where('name', 'Shift Pagi Petra')->firstOrFail();
        $this->assertSame(['Ruang Chief', 'Ruang Audit'], $template->items->pluck('room')->all());
    }

    public function test_office_boy_cannot_open_the_template_page(): void
    {
        $this->assertFalse($this->officeBoy()->can('viewAny', ObTaskTemplate::class));
    }

    public function test_only_assigners_can_change_the_petugas_on_edit(): void
    {
        $officeBoy = $this->officeBoy();
        $task = $this->pendingTask($officeBoy, 'Ruang Audit');
        $editor = $this->userWithPermissions(['view_any_ob::checksheet', 'view_ob::checksheet', 'update_ob::checksheet', 'update_all_ob::checksheet', 'view_all_ob::checksheet']);

        $this->actAs($editor);

        Livewire::test(ObChecksheetResource\Pages\EditObChecksheet::class, ['record' => $task->getKey()])
            ->assertFormFieldIsDisabled('user_id');

        $this->actAs($this->supervisor());

        Livewire::test(ObChecksheetResource\Pages\EditObChecksheet::class, ['record' => $task->getKey()])
            ->assertFormFieldIsEnabled('user_id');
    }

    public function test_template_without_a_petugas_goes_to_the_shared_pool(): void
    {
        $supervisor = $this->supervisor();
        $template = $this->template('Shift Pagi Petra', ['Ruang Chief', 'Ruang Audit']);

        $this->actAs($supervisor);

        Livewire::test(ListObChecksheets::class)
            ->callAction('assignFromTemplate', [
                'template_id' => $template->id,
                'user_id' => null,
                'date' => today()->toDateString(),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('2 tugas dibuat');

        $tasks = ObChecksheet::query()->where('shift_label', 'Shift Pagi Petra')->orderBy('id')->get();

        $this->assertCount(2, $tasks);
        $this->assertTrue($tasks->every(fn (ObChecksheet $task): bool => $task->user_id === null
            && $task->status === ObChecksheet::STATUS_PENDING
            && $task->source === ObChecksheet::SOURCE_ASSIGNED
            && $task->assigned_by === $supervisor->id));
        $this->assertTrue($tasks->every(fn (ObChecksheet $task): bool => $task->isUnclaimed()));
    }

    public function test_manual_task_without_a_petugas_goes_to_the_shared_pool(): void
    {
        $this->actAs($this->supervisor());

        $this->callWithRepeater(
            Livewire::test(ListObChecksheets::class),
            'assignManual',
            'rooms',
            ['user_id' => null, 'date' => today()->toDateString()],
            [['room' => 'Ruang Tamu Pool']],
        )->assertHasNoActionErrors();

        $this->assertNull(ObChecksheet::query()->where('room', 'Ruang Tamu Pool')->firstOrFail()->user_id);
    }

    public function test_pool_assignment_skips_rooms_even_after_someone_claimed_them(): void
    {
        [$supervisor, $officeBoy] = [$this->supervisor(), $this->officeBoy()];
        $template = $this->template('Shift Pagi', ['Ruang Audit', 'Aula']);
        $service = app(ObTaskAssignmentService::class);

        $this->assertSame(['created' => 2, 'skipped' => 0], $service->assignTemplate($template, null, now(), $supervisor));

        ObChecksheet::query()->where('room', 'Ruang Audit')->firstOrFail()->claim($officeBoy);

        $this->assertSame(['created' => 0, 'skipped' => 2], $service->assignTemplate($template, null, now(), $supervisor));
        $this->assertSame(2, ObChecksheet::query()->where('shift_label', 'Shift Pagi')->count());
    }

    public function test_every_office_boy_sees_unclaimed_pool_tasks_but_not_tasks_owned_by_others(): void
    {
        [$budi, $sari] = [$this->officeBoy(), $this->officeBoy()];
        $pool = $this->poolTask('Ruang Pool');
        $budiTask = $this->pendingTask($budi, 'Ruang Budi');

        $this->actAs($sari);
        Livewire::test(ListObChecksheets::class)
            ->assertCanSeeTableRecords([$pool])
            ->assertCanNotSeeTableRecords([$budiTask]);

        $this->actAs($budi);
        Livewire::test(ListObChecksheets::class)
            ->assertCanSeeTableRecords([$pool, $budiTask]);
    }

    public function test_claim_is_won_by_the_first_office_boy_only(): void
    {
        [$budi, $sari] = [$this->officeBoy(), $this->officeBoy()];
        $forBudi = $this->poolTask('Ruang Pool');
        $forSari = ObChecksheet::findOrFail($forBudi->id);

        $this->assertTrue($forBudi->claim($budi));
        $this->assertFalse($forSari->claim($sari));

        $this->assertSame($budi->id, $forSari->refresh()->user_id);
        $this->assertTrue($forBudi->claim($budi), 'Mengambil ulang tugas sendiri tidak gagal.');
    }

    public function test_claimed_task_is_not_visible_to_other_office_boys_anymore(): void
    {
        [$budi, $sari] = [$this->officeBoy(), $this->officeBoy()];
        $task = $this->poolTask('Ruang Pool');
        $task->claim($budi);

        $this->actAs($sari);

        Livewire::test(ListObChecksheets::class)->assertCanNotSeeTableRecords([$task]);
    }

    public function test_start_all_claims_pool_tasks_for_the_office_boy_who_photographs_them(): void
    {
        $officeBoy = $this->officeBoy();
        $pool = $this->poolTask('Ruang Pool');
        $untouched = $this->poolTask('Ruang Lain');

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->assertActionVisible('startAll')
            ->callAction('startAll', [
                'tasks' => [
                    ['id' => $pool->id, 'room' => 'Ruang Pool', 'photo' => 'ob-checksheets/before/pool.jpg', 'notes' => null],
                    ['id' => $untouched->id, 'room' => 'Ruang Lain', 'photo' => null, 'notes' => null],
                ],
            ])
            ->assertNotified('1 ruangan dimulai');

        $pool->refresh();
        $untouched->refresh();

        $this->assertSame($officeBoy->id, $pool->user_id);
        $this->assertSame(ObChecksheet::STATUS_IN_PROGRESS, $pool->status);
        $this->assertNull($untouched->user_id, 'Ruangan tanpa foto tetap di kolam.');
        $this->assertSame(ObChecksheet::STATUS_PENDING, $untouched->status);
    }

    public function test_start_all_does_not_steal_a_pool_task_claimed_by_another_office_boy(): void
    {
        [$budi, $sari] = [$this->officeBoy(), $this->officeBoy()];
        $task = $this->poolTask('Ruang Pool');
        $this->poolTask('Ruang Lain'); // menjaga tombol tetap tampil untuk Sari setelah Budi mengambil

        // Sari membuka form saat tugas masih di kolam, lalu Budi mengambilnya lebih dulu.
        $this->actAs($sari);
        $sariForm = Livewire::test(ListObChecksheets::class)->mountAction('startAll');

        $task->claim($budi);
        $task->startWork('ob-checksheets/before/budi.jpg');

        $sariForm
            ->setActionData(['tasks' => [['id' => $task->id, 'room' => 'Ruang Pool', 'photo' => 'ob-checksheets/before/sari.jpg', 'notes' => null]]])
            ->callMountedAction()
            ->assertNotified('0 ruangan dimulai');

        $task->refresh();
        $this->assertSame($budi->id, $task->user_id);
        $this->assertSame('ob-checksheets/before/budi.jpg', $task->before_photo);
    }

    public function test_unclaimed_pool_tasks_from_earlier_days_can_still_be_taken(): void
    {
        $officeBoy = $this->officeBoy();
        $old = $this->poolTask('Ruang Kemarin', now()->subDays(2));
        $future = $this->poolTask('Ruang Besok', now()->addDay());

        $this->actAs($officeBoy);

        Livewire::test(ListObChecksheets::class)
            ->callAction('startAll', [
                'tasks' => [
                    ['id' => $old->id, 'room' => 'Ruang Kemarin', 'photo' => 'ob-checksheets/before/old.jpg', 'notes' => null],
                    ['id' => $future->id, 'room' => 'Ruang Besok', 'photo' => 'ob-checksheets/before/future.jpg', 'notes' => null],
                ],
            ])
            ->assertNotified('1 ruangan dimulai');

        $this->assertSame($officeBoy->id, $old->refresh()->user_id);
        $this->assertNull($future->refresh()->user_id, 'Tugas untuk hari berikutnya belum bisa diambil.');
    }

    public function test_row_start_action_claims_a_pool_task_and_the_slower_office_boy_is_told(): void
    {
        [$budi, $sari] = [$this->officeBoy(), $this->officeBoy()];
        $task = $this->poolTask('Ruang Pool');

        $this->actAs($budi);
        Livewire::test(ListObChecksheets::class)
            ->assertTableActionVisible('start', $task)
            ->callTableAction('start', $task, ['before_photo' => 'ob-checksheets/before/budi.jpg'])
            ->assertHasNoTableActionErrors();

        $this->assertSame($budi->id, $task->refresh()->user_id);
        $this->assertSame(ObChecksheet::STATUS_IN_PROGRESS, $task->status);

        // Sari masih memegang halaman lama: tugasnya sudah milik Budi.
        $stale = ObChecksheet::findOrFail($task->id);
        $stale->forceFill(['user_id' => null, 'status' => ObChecksheet::STATUS_PENDING])->syncOriginal();

        $this->assertFalse($stale->claim($sari));
        $this->assertSame($budi->id, $task->refresh()->user_id);
    }

    public function test_only_the_taker_can_finish_a_claimed_pool_task(): void
    {
        [$budi, $sari] = [$this->officeBoy(), $this->officeBoy()];
        $task = $this->poolTask('Ruang Pool');
        $task->claim($budi);
        $task->startWork('ob-checksheets/before/pool.jpg');

        $this->actAs($sari);
        Livewire::test(ListObChecksheets::class)
            ->assertActionHidden('finishAll')
            ->assertCanNotSeeTableRecords([$task]);

        $this->actAs($budi);
        Livewire::test(ListObChecksheets::class)
            ->assertTableActionVisible('complete', $task)
            ->callAction('finishAll', [
                'tasks' => [['id' => $task->id, 'room' => 'Ruang Pool', 'photo' => 'ob-checksheets/after/pool.jpg', 'notes' => null]],
            ])
            ->assertNotified('1 ruangan selesai');

        $this->assertSame(ObChecksheet::STATUS_COMPLETED, $task->refresh()->status);
    }

    public function test_supervisor_can_finish_on_behalf_of_the_taker(): void
    {
        $task = $this->poolTask('Ruang Pool');
        $task->claim($this->officeBoy());
        $task->startWork('ob-checksheets/before/pool.jpg');

        $this->actAs($this->supervisor());

        Livewire::test(ListObChecksheets::class)->assertTableActionVisible('complete', $task);
    }

    public function test_supervisor_returns_pending_tasks_to_the_pool_but_not_started_ones(): void
    {
        $supervisor = $this->supervisor();
        $officeBoy = $this->officeBoy();
        $pending = $this->pendingTask($officeBoy, 'Ruang Audit');
        $running = $this->inProgressTask($officeBoy, 'Aula');

        $this->actAs($supervisor);

        Livewire::test(ListObChecksheets::class)
            ->callTableBulkAction('reassign', [$pending, $running], ['user_id' => null])
            ->assertHasNoTableBulkActionErrors();

        $this->assertNull($pending->refresh()->user_id);
        $this->assertSame($officeBoy->id, $running->refresh()->user_id);
    }

    public function test_unclaimed_filter_and_label_for_the_supervisor(): void
    {
        $supervisor = $this->supervisor();
        $pool = $this->poolTask('Ruang Pool');
        $owned = $this->pendingTask($this->officeBoy(), 'Ruang Milik');

        $this->actAs($supervisor);

        Livewire::test(ListObChecksheets::class)
            ->assertCanSeeTableRecords([$pool, $owned])
            ->assertSee('Belum diambil')
            ->filterTable('unclaimed')
            ->assertCanSeeTableRecords([$pool])
            ->assertCanNotSeeTableRecords([$owned]);
    }

    public function test_office_boy_cannot_claim_through_the_edit_or_export_scope(): void
    {
        $officeBoy = $this->officeBoy();
        $pool = $this->poolTask('Ruang Pool');
        $someoneElse = $this->pendingTask($this->officeBoy(), 'Ruang Orang Lain');

        $this->actAs($officeBoy);

        $ids = ObChecksheetExporter::modifyQuery(ObChecksheet::query())->pluck('id')->all();

        $this->assertContains($pool->id, $ids);
        $this->assertNotContains($someoneElse->id, $ids);

        $exporter = new ObChecksheetExporter(
            new Export(['file_disk' => 'local', 'exporter' => ObChecksheetExporter::class, 'total_rows' => 1, 'user_id' => $officeBoy->id]),
            ['user.name' => 'Petugas OB'],
            [],
        );

        $this->assertContains('Belum diambil', $exporter($pool));
    }

    /**
     * Repeater dengan item default menggabungkan isian test ke item kosong itu,
     * jadi daftar itemnya diganti utuh sebelum action dipanggil.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    private function callWithRepeater(Testable $test, string $action, string $repeater, array $data, array $items): Testable
    {
        return $test->mountAction($action)
            ->set("mountedActions.0.data.{$repeater}", $items)
            ->setActionData($data)
            ->callMountedAction();
    }

    private function actAs(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function supervisor(): User
    {
        return $this->userWithPermissions(self::SUPERVISOR_PERMISSIONS);
    }

    private function officeBoy(): User
    {
        $user = User::factory()->create();
        $user->assignRole($this->role('office_boy', self::OFFICE_BOY_PERMISSIONS));

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->assignRole($this->role('__ob_test_'.md5(implode(',', $permissions)).'__', $permissions));

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function role(string $name, array $permissions): Role
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::findOrCreate($name, 'web');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    /**
     * @param  list<string>  $rooms
     */
    private function template(string $name, array $rooms): ObTaskTemplate
    {
        $template = ObTaskTemplate::create(['name' => $name, 'shift_label' => $name, 'is_active' => true]);

        foreach ($rooms as $index => $room) {
            $template->items()->create(['room' => $room, 'sort_order' => $index]);
        }

        return $template;
    }

    private function pendingTask(User $officeBoy, string $room, mixed $date = null): ObChecksheet
    {
        return ObChecksheet::create([
            'user_id' => $officeBoy->id,
            'source' => ObChecksheet::SOURCE_ASSIGNED,
            'status' => ObChecksheet::STATUS_PENDING,
            'scheduled_date' => ($date ?? now())->toDateString(),
            'room' => $room,
        ]);
    }

    /**
     * Tugas atasan tanpa petugas (kolam bersama).
     */
    private function poolTask(string $room, mixed $date = null): ObChecksheet
    {
        return ObChecksheet::create([
            'user_id' => null,
            'source' => ObChecksheet::SOURCE_ASSIGNED,
            'status' => ObChecksheet::STATUS_PENDING,
            'scheduled_date' => ($date ?? now())->toDateString(),
            'room' => $room,
        ]);
    }

    private function inProgressTask(User $officeBoy, string $room): ObChecksheet
    {
        $task = $this->pendingTask($officeBoy, $room);
        $task->startWork('ob-checksheets/before/'.str($room)->slug().'.jpg');

        return $task;
    }
}

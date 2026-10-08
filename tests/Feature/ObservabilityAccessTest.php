<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ObservabilityAccess;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ObservabilityAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);

        foreach (array_keys(ObservabilityAccess::shieldPermissions()) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_guests_cannot_access_horizon_or_log_viewer(): void
    {
        $this->assertFalse(ObservabilityAccess::allowed(null));
        $this->assertSame('horizon', config('horizon.path'));
        $this->assertSame('log-viewer', config('log-viewer.route_path'));
        $this->assertTrue(Gate::has('viewLogViewer'));
        $this->assertTrue(Gate::has('viewHorizon'));
    }

    public function test_filament_items_are_horizon_and_log_viewer_without_nav_group(): void
    {
        $items = ObservabilityAccess::filamentNavigationItems();

        $this->assertCount(2, $items);
        $this->assertSame('Horizon', $items[0]->getLabel());
        $this->assertSame('Log Viewer', $items[1]->getLabel());
        $this->assertNull($items[0]->getGroup());
        $this->assertNull($items[1]->getGroup());
        $this->assertStringContainsString('/horizon', $items[0]->getUrl());
        $this->assertStringContainsString('/log-viewer', $items[1]->getUrl());
    }

    public function test_observability_permissions_are_shield_custom_permissions(): void
    {
        $this->assertTrue(config('filament-shield.shield_resource.tabs.custom_permissions'));
        $this->assertSame(
            ['View:Horizon', 'View:LogViewer', 'Download:LogViewer', 'Delete:LogViewer'],
            array_keys(FilamentShield::getCustomPermissions()),
        );
    }

    public function test_observability_permission_labels_follow_the_app_locale(): void
    {
        $expected = [
            'id' => ['Lihat Horizon', 'Lihat Log Viewer', 'Unduh File Log', 'Hapus File Log'],
            'en' => ['View Horizon', 'View Log Viewer', 'Download Log Files', 'Delete Log Files'],
        ];

        foreach ($expected as $locale => $labels) {
            app()->setLocale($locale);

            $this->assertSame($labels, array_map(
                fn (string $permission, string $label): string => FilamentShield::getCustomPermissionLabel($permission, $label),
                array_keys(ObservabilityAccess::shieldPermissions()),
                ObservabilityAccess::shieldPermissions(),
            ), $locale);
        }
    }

    public function test_super_admin_can_access_every_observability_tool(): void
    {
        $user = $this->userWithRole(config('filament-shield.super_admin.name'));

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
        $this->assertTrue(Gate::forUser($user)->allows('viewLogViewer'));
        $this->assertTrue(Gate::forUser($user)->allows('downloadLogFile'));
        $this->assertTrue(Gate::forUser($user)->allows('deleteLogFolder'));
    }

    public function test_role_without_observability_permissions_is_denied(): void
    {
        $user = $this->userWithRole('__observability_none__');

        $this->assertFalse(ObservabilityAccess::allowed($user));
        $this->assertFalse(Gate::forUser($user)->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($user)->allows('viewLogViewer'));
    }

    public function test_horizon_and_log_viewer_are_granted_independently(): void
    {
        $horizonOnly = $this->userWithRole('__observability_horizon__', [ObservabilityAccess::VIEW_HORIZON]);
        $logsOnly = $this->userWithRole('__observability_logs__', [ObservabilityAccess::VIEW_LOG_VIEWER]);

        $this->assertTrue(Gate::forUser($horizonOnly)->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser($horizonOnly)->allows('viewLogViewer'));
        $this->assertTrue(ObservabilityAccess::allowed($horizonOnly));

        $this->assertFalse(Gate::forUser($logsOnly)->allows('viewHorizon'));
        $this->assertTrue(Gate::forUser($logsOnly)->allows('viewLogViewer'));
        $this->assertFalse(Gate::forUser($logsOnly)->allows('downloadLogFile'));
        $this->assertFalse(Gate::forUser($logsOnly)->allows('deleteLogFile'));
    }

    public function test_log_download_and_delete_require_view_permission(): void
    {
        $withoutView = $this->userWithRole('__observability_no_view__', [
            ObservabilityAccess::DOWNLOAD_LOGS,
            ObservabilityAccess::DELETE_LOGS,
        ]);
        $withView = $this->userWithRole('__observability_full_logs__', [
            ObservabilityAccess::VIEW_LOG_VIEWER,
            ObservabilityAccess::DOWNLOAD_LOGS,
            ObservabilityAccess::DELETE_LOGS,
        ]);

        $this->assertFalse(Gate::forUser($withoutView)->allows('downloadLogFile'));
        $this->assertFalse(Gate::forUser($withoutView)->allows('deleteLogFile'));

        foreach (['downloadLogFile', 'downloadLogFolder', 'deleteLogFile', 'deleteLogFolder'] as $ability) {
            $this->assertTrue(Gate::forUser($withView)->allows($ability), $ability);
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithRole(string $roleName, array $permissions = []): User
    {
        $role = Role::findOrCreate($roleName, 'web');
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }
}

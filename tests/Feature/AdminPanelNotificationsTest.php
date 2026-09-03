<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Tests\TestCase;

class AdminPanelNotificationsTest extends TestCase
{
    public function test_admin_panel_enables_database_notifications(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue(
            $panel->hasDatabaseNotifications(),
            'Database notifications must be enabled so the topbar notification button is visible.',
        );
    }

    public function test_database_notifications_are_eager_and_poll_quickly(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse($panel->hasLazyLoadedDatabaseNotifications());
        $this->assertSame('2s', $panel->getDatabaseNotificationsPollingInterval());
    }

    public function test_export_started_notification_tells_user_to_open_the_bell(): void
    {
        $this->app->setLocale('id');

        $this->assertStringContainsString(
            'lonceng',
            trans_choice('filament-actions::export.notifications.started.body', 17, ['count' => 17]),
        );
    }

    public function test_local_disk_private_files_are_group_readable_by_www_data(): void
    {
        $permissions = config('filesystems.disks.local.permissions');

        $this->assertSame(0660, $permissions['file']['private']);
        $this->assertSame(0770, $permissions['dir']['private']);
    }
}

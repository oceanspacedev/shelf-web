<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ObservabilityPackagesTest extends TestCase
{
    public function test_composer_requires_horizon_and_log_viewer(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

        $this->assertIsArray($composer);
        $this->assertArrayHasKey('laravel/horizon', $composer['require']);
        $this->assertArrayHasKey('opcodesio/log-viewer', $composer['require']);
    }

    public function test_providers_and_support_classes_are_wired(): void
    {
        $root = dirname(__DIR__, 2);
        $providers = (string) file_get_contents($root.'/bootstrap/providers.php');

        $this->assertFileExists($root.'/app/Providers/HorizonServiceProvider.php');
        $this->assertFileExists($root.'/app/Support/ObservabilityAccess.php');
        $this->assertFileExists($root.'/config/horizon.php');
        $this->assertFileExists($root.'/config/log-viewer.php');
        $this->assertStringContainsString('HorizonServiceProvider::class', $providers);
        $this->assertStringContainsString('filament.mekaya-sidebar-footer', (string) file_get_contents($root.'/resources/views/vendor/mekaya/livewire/mekaya-sidebar.blade.php'));
        $this->assertStringContainsString('filament.observability-sidebar-footer', (string) file_get_contents($root.'/resources/views/filament/mekaya-sidebar-footer.blade.php'));
        $this->assertStringContainsString('ObservabilityAccess::filamentNavigationItems()', (string) file_get_contents($root.'/resources/views/filament/observability-sidebar-footer.blade.php'));
        $this->assertStringContainsString('mky-sidebar-group', (string) file_get_contents($root.'/resources/views/filament/observability-sidebar-footer.blade.php'));
        $this->assertStringNotContainsString('mky-si-nav', (string) file_get_contents($root.'/resources/views/filament/observability-sidebar-footer.blade.php'));
        $this->assertStringNotContainsString('navigationItems(ObservabilityAccess::filamentNavigationItems())', (string) file_get_contents($root.'/app/Providers/Filament/AdminPanelProvider.php'));
        $this->assertStringContainsString('horizon:snapshot', (string) file_get_contents($root.'/routes/console.php'));
        $horizon = (string) file_get_contents($root.'/config/horizon.php');
        $this->assertStringContainsString("'queue' => ['notifications', 'default', 'exports']", $horizon);
        $this->assertStringNotContainsString('supervisor-notifications', $horizon);
        $this->assertStringNotContainsString('supervisor-exports', $horizon);
    }
}

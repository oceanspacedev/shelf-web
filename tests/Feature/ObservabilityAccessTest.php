<?php

namespace Tests\Feature;

use App\Support\ObservabilityAccess;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ObservabilityAccessTest extends TestCase
{
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
}

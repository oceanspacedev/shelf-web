<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class OverlookThemeTest extends TestCase
{
    public function test_overlook_stat_cards_have_the_same_number_layout_as_the_helpdesk_admin(): void
    {
        $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

        $this->assertStringContainsString("@source '../../../../vendor/awcodes/overlook/resources/views/**/*.blade.php';", $theme);
        $this->assertStringContainsString('#overlook-widget .overlook-count', $theme);
        $this->assertStringContainsString('font-size: 1.875rem;', $theme);
        $this->assertStringContainsString('font-weight: 700;', $theme);
        $this->assertStringContainsString('line-height: 1;', $theme);
        $this->assertStringContainsString('min-height: 8rem;', $theme);
        $this->assertStringContainsString('background: rgb(255 255 255) !important;', $theme);
        $this->assertStringContainsString('box-shadow: 0 0 0 6px rgb(249 250 251), 0 0 0 7px rgb(229 231 235) !important;', $theme);
        $this->assertStringContainsString('.dark #overlook-widget .overlook-card', $theme);
        $this->assertStringContainsString('box-shadow: 0 0 0 6px rgb(3 7 18), 0 0 0 7px rgb(255 255 255 / 0.1) !important;', $theme);
        $this->assertStringContainsString('padding: 1rem;', $theme);
    }
}

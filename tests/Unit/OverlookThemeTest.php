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
        $this->assertStringContainsString('font-size: 2.25rem;', $theme);
        $this->assertStringContainsString('font-weight: 700;', $theme);
        $this->assertStringContainsString('line-height: 1;', $theme);
        $this->assertStringContainsString('border: 1px solid rgb(228 228 231);', $theme);
        $this->assertStringContainsString('background: white;', $theme);
        $this->assertStringContainsString('padding: 1.5rem;', $theme);
        $this->assertStringContainsString('gap: 0.75rem;', $theme);
    }
}

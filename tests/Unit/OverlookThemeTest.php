<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class OverlookThemeTest extends TestCase
{
    public function test_overlook_stat_cards_match_mekaya_login_card_layers(): void
    {
        $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

        $this->assertStringContainsString("@source '../../../../vendor/awcodes/overlook/resources/views/**/*.blade.php';", $theme);
        $this->assertStringContainsString('#overlook-widget .overlook-count', $theme);
        $this->assertStringContainsString('font-size: 1.875rem;', $theme);
        $this->assertStringContainsString('font-weight: 700;', $theme);
        $this->assertStringContainsString('line-height: 1;', $theme);
        $this->assertStringContainsString('min-height: 8rem;', $theme);
        $this->assertStringContainsString('border-radius: 0.75rem !important;', $theme);
        $this->assertStringContainsString('background: var(--color-white, #fff) !important;', $theme);
        $this->assertStringContainsString('0 0 0 6px var(--color-gray-50)', $theme);
        $this->assertStringContainsString('0 0 0 7px var(--color-gray-200)', $theme);
        $this->assertStringContainsString('color-mix(in oklab, var(--color-gray-700) 20%, transparent)', $theme);
        $this->assertStringContainsString('.dark #overlook-widget .overlook-card', $theme);
        $this->assertStringContainsString('background: var(--color-gray-900) !important;', $theme);
        $this->assertStringContainsString('0 0 0 6px var(--color-gray-950)', $theme);
        $this->assertStringContainsString('0 0 0 7px rgb(255 255 255 / 0.1)', $theme);
        $this->assertStringContainsString('padding: 1rem;', $theme);
        $this->assertStringNotContainsString('rgb(17 24 39)', $theme);
        $this->assertStringNotContainsString('rgb(3 7 18)', $theme);
    }
}

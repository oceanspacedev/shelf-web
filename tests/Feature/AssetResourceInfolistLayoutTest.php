<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use Filament\Infolists\Components\Grid as ComponentsGrid;
use Filament\Infolists\Components\Section as ComponentsSection;
use Filament\Infolists\Infolist;
use Tests\TestCase;

class AssetResourceInfolistLayoutTest extends TestCase
{
    public function test_asset_view_infolist_uses_three_column_desktop_layout(): void
    {
        $infolist = AssetResource::infolist(Infolist::make());

        $this->assertSame(3, $infolist->getColumns('lg'));

        $layoutGroups = array_values($infolist->getComponents(withHidden: true));

        $this->assertCount(2, $layoutGroups);
        $this->assertInstanceOf(ComponentsGrid::class, $layoutGroups[0]);
        $this->assertInstanceOf(ComponentsGrid::class, $layoutGroups[1]);
        $this->assertSame(2, $layoutGroups[0]->getColumnSpan('lg'));
        $this->assertSame(1, $layoutGroups[1]->getColumnSpan('lg'));
    }

    public function test_status_nbh_section_uses_single_column_details(): void
    {
        $infolist = AssetResource::infolist(Infolist::make());
        $layoutGroups = array_values($infolist->getComponents(withHidden: true));

        $statusSection = collect($layoutGroups[1]->getChildComponents())
            ->first(fn ($component) => $component instanceof ComponentsSection && $component->getHeading() === 'Status & NBH');

        $this->assertNotNull($statusSection);

        $statusGrids = array_values(array_filter(
            $statusSection->getChildComponents(),
            fn ($component) => $component instanceof ComponentsGrid,
        ));

        $this->assertCount(3, $statusGrids);

        foreach ($statusGrids as $grid) {
            $this->assertSame(1, $grid->getColumns('lg'));
        }
    }
}

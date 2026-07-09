<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Enums\NbhStatus;
use App\Models\Asset;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Components\Grid as ComponentsGrid;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;
use Livewire\Component;
use Tests\TestCase;

class AssetResourceInfolistLayoutTest extends TestCase
{
    public function test_asset_view_infolist_uses_three_column_desktop_layout(): void
    {
        $infolist = AssetResource::infolist(Schema::make($this->makeSchemaLivewire()));

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
        $infolist = AssetResource::infolist(
            Schema::make($this->makeSchemaLivewire())
                ->record(new Asset(['nbh_status' => NbhStatus::Pending])),
        );
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

    private function makeSchemaLivewire(): Component&HasSchemas
    {
        return new class extends Component implements HasSchemas {
            use InteractsWithSchemas;

            public function render(): string
            {
                return '';
            }
        };
    }
}

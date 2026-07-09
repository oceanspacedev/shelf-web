<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetRequestResource;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Tests\TestCase;

class AssetRequestResourceInfolistLayoutTest extends TestCase
{
    public function test_asset_request_view_uses_cesa_style_two_column_infolist_layout(): void
    {
        $infolist = AssetRequestResource::infolist(Schema::make($this->makeSchemaLivewire()));

        $this->assertSame(3, $infolist->getColumns('lg'));

        $layoutGroups = array_values($infolist->getComponents(withHidden: true));

        $this->assertCount(2, $layoutGroups);
        $this->assertInstanceOf(Grid::class, $layoutGroups[0]);
        $this->assertInstanceOf(Grid::class, $layoutGroups[1]);
        $this->assertSame(2, $layoutGroups[0]->getColumnSpan('lg'));
        $this->assertSame(1, $layoutGroups[1]->getColumnSpan('lg'));
    }

    public function test_asset_request_view_shows_related_items_and_approvals_as_repeatable_tracking_sections(): void
    {
        $infolist = AssetRequestResource::infolist(Schema::make($this->makeSchemaLivewire()));
        $layoutGroups = array_values($infolist->getComponents(withHidden: true));

        $itemSection = collect($layoutGroups[0]->getChildComponents())
            ->first(fn ($component) => $component instanceof Section && $component->getHeading() === 'Item Pengajuan');

        $approvalSection = collect($layoutGroups[1]->getChildComponents())
            ->first(fn ($component) => $component instanceof Section && $component->getHeading() === 'Approval Tracking');

        $this->assertNotNull($itemSection);
        $this->assertNotNull($approvalSection);

        $this->assertTrue(collect($itemSection->getChildComponents())->contains(
            fn ($component) => $component instanceof RepeatableEntry && $component->getName() === 'items'
        ));

        $this->assertTrue(collect($approvalSection->getChildComponents())->contains(
            fn ($component) => $component instanceof RepeatableEntry && $component->getName() === 'approvals'
        ));
    }

    public function test_asset_request_approval_tracking_does_not_show_internal_level_labels(): void
    {
        $infolist = AssetRequestResource::infolist(Schema::make($this->makeSchemaLivewire()));
        $layoutGroups = array_values($infolist->getComponents(withHidden: true));

        $approvalSection = collect($layoutGroups[1]->getChildComponents())
            ->first(fn ($component) => $component instanceof Section && $component->getHeading() === 'Approval Tracking');

        $this->assertNotNull($approvalSection);

        $entryNames = $this->textEntryNames($approvalSection->getChildComponents());

        $this->assertNotContains('current_pending_approval', $entryNames);
        $this->assertNotContains('level', $entryNames);
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array<int, string>
     */
    private function textEntryNames(array $components): array
    {
        $names = [];

        foreach ($components as $component) {
            if ($component instanceof TextEntry) {
                $names[] = $component->getName();
            }

            if (method_exists($component, 'getChildComponents')) {
                $names = array_merge($names, $this->textEntryNames($component->getChildComponents()));
            }
        }

        return $names;
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

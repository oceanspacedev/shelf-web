<?php

namespace Tests\Feature;

use App\Enums\AssetRequestType;
use App\Enums\RequestStatus;
use App\Filament\Resources\AssetRequestResource;
use App\Filament\Resources\AssetRequestResource\Pages\ViewAssetRequest;
use App\Models\AssetRequest;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Mockery;
use Tests\TestCase;

class AssetRequestActionLayoutTest extends TestCase
{
    public function test_list_prioritizes_contextual_workflow_and_moves_secondary_actions_into_overflow(): void
    {
        $table = $this->makeTable();
        $actions = $table->getRecordActions();

        $this->assertSame([
            'review',
            'fulfillPengadaan',
            'fulfillPenarikan',
            'fulfillPerbaikan',
            'view',
            '@group',
        ], $this->topLevelActionNames($actions));

        $this->assertTrue($actions[4]->isIconButton());

        $overflow = $actions[5];

        $this->assertInstanceOf(ActionGroup::class, $overflow);
        $this->assertTrue($overflow->isIconButton());
        $this->assertSame('Aksi lainnya', $overflow->getLabel());
        $this->assertSame([
            'edit',
            'openPublicProgress',
            'resendApprovalNotification',
            'resendRequesterNotification',
            'delete',
        ], array_keys($overflow->getFlatActions()));
    }

    public function test_list_hides_supporting_columns_by_default_to_keep_the_workflow_readable(): void
    {
        $columns = $this->makeTable()->getColumns();

        $this->assertFalse($columns['reference_number']->isToggledHiddenByDefault());
        $this->assertFalse($columns['status']->isToggledHiddenByDefault());
        $this->assertFalse($columns['lifecycle_stage']->isToggledHiddenByDefault());

        $this->assertTrue($columns['division.name']->isToggledHiddenByDefault());
        $this->assertTrue($columns['assetLocation.name']->isToggledHiddenByDefault());
        $this->assertTrue($columns['qty']->isToggledHiddenByDefault());
    }

    public function test_detail_exposes_contextual_actions_and_moves_destructive_actions_into_overflow(): void
    {
        $actions = $this->detailActionsFor(new AssetRequest([
            'type' => AssetRequestType::Pengadaan,
            'status' => RequestStatus::Pending,
        ]));

        $this->assertSame([
            'approve',
            'reject',
            'fulfillPengadaan',
            'fulfillPenarikan',
            'fulfillPerbaikan',
            'downloadPengadaan',
            'edit',
            '@group',
        ], $this->topLevelActionNames($actions));

        $overflow = $actions[7];

        $this->assertInstanceOf(ActionGroup::class, $overflow);
        $this->assertTrue($overflow->isButton());
        $this->assertSame('Aksi lainnya', $overflow->getLabel());
        $this->assertSame([
            'openPublicProgress',
            'resendApprovalNotification',
            'resendRequesterNotification',
            'delete',
            'forceDelete',
            'restore',
        ], array_keys($overflow->getFlatActions()));
    }

    public function test_detail_hides_repair_follow_up_when_no_requested_asset_exists(): void
    {
        $record = new class([
            'type' => AssetRequestType::Perbaikan,
            'status' => RequestStatus::Approved,
            'fulfilled_at' => null,
        ]) extends AssetRequest {
            public function requestedAssetIds(): array
            {
                return [];
            }
        };

        $actions = $this->flattenActions($this->detailActionsFor($record));

        $this->assertFalse($actions['fulfillPerbaikan']->isVisible());
    }

    public function test_detail_provides_public_progress_as_a_secondary_new_tab_action(): void
    {
        $record = new AssetRequest([
            'type' => AssetRequestType::Penarikan,
            'status' => RequestStatus::Pending,
            'public_token' => 'progress-token',
        ]);

        $actions = $this->flattenActions($this->detailActionsFor($record));

        $this->assertArrayHasKey('openPublicProgress', $actions);
        $this->assertSame(route('public.asset-requests.show', 'progress-token'), $actions['openPublicProgress']->getUrl());
        $this->assertTrue($actions['openPublicProgress']->shouldOpenUrlInNewTab());
    }

    private function makeTable(): Table
    {
        return AssetRequestResource::table(Table::make(Mockery::mock(HasTable::class)));
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    private function detailActionsFor(AssetRequest $record): array
    {
        $page = new class extends ViewAssetRequest {
            /**
             * @return array<int, Action|ActionGroup>
             */
            public function actionsFor(AssetRequest $record): array
            {
                $this->record = $record;

                return $this->getHeaderActions();
            }
        };

        return $page->actionsFor($record);
    }

    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<int, string>
     */
    private function topLevelActionNames(array $actions): array
    {
        return array_map(
            fn (Action|ActionGroup $action): string => $action instanceof ActionGroup ? '@group' : $action->getName(),
            $actions,
        );
    }

    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<string, Action>
     */
    private function flattenActions(array $actions): array
    {
        $flat = [];

        foreach ($actions as $action) {
            if ($action instanceof ActionGroup) {
                $flat = [...$flat, ...$action->getFlatActions()];

                continue;
            }

            $flat[$action->getName()] = $action;
        }

        return $flat;
    }
}

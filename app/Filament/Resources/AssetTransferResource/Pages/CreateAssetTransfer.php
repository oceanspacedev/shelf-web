<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Filament\Resources\AssetTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateAssetTransfer extends CreateRecord
{
    protected static string $resource = AssetTransferResource::class;

    protected function afterCreate(): void
    {
        DB::transaction(function () {
            $record = $this->record;
            $toUser = $record->toUser;
            $hasGeneralAffairRole = $toUser->hasRole('general_affair');

            foreach ($record->details as $detail) {
                $asset = $detail->asset;
                $asset->recipient_id = $record->to_user_id;
                $asset->recipient_business_entity_id = $record->business_entity_id;
                $asset->condition_status = $hasGeneralAffairRole
                    ? AssetCondition::Available
                    : AssetCondition::Transferred;
                if ($asset->condition_status instanceof AssetCondition && $asset->condition_status->isTransferable()) {
                    $asset->nbh_status = NbhStatus::None;
                    $asset->nbh_responsible_user_id = null;
                }
                $asset->save();
            }
        });
    }
}

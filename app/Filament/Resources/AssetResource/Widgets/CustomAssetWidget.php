<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Enums\AssetCondition;
use App\Models\Asset;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CustomAssetWidget extends BaseWidget
{
    use HasWidgetShield;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $availableUnits = Asset::where('condition_status', AssetCondition::Available->value)->count();
        $transferredUnits = Asset::where('condition_status', AssetCondition::Transferred->value)->count();
        $soldUnits = Asset::where('condition_status', AssetCondition::Sold->value)->count();
        $lostUnits = Asset::where('condition_status', AssetCondition::Lost->value)->count();
        $damagedUnits = Asset::where('condition_status', AssetCondition::Damaged->value)->count();
        $totalAssets = Asset::count();
        $activeAssetValue = Asset::whereIn('condition_status', AssetCondition::transferableValues())
            ->sum(DB::raw('item_price * qty'));
        $soldAssetValue = Asset::where('condition_status', AssetCondition::Sold->value)
            ->sum(DB::raw('item_price * qty'));

        return [
            Stat::make(__('Aset Tersedia'), $availableUnits)->color('success'),
            Stat::make(__('Aset Digunakan'), $transferredUnits)->color('warning'),
            Stat::make(__('Aset Dijual'), $soldUnits)->color('gray'),
            Stat::make(__('Aset Hilang'), $lostUnits)->color('danger'),
            Stat::make(__('Aset Rusak'), $damagedUnits)->color('danger'),
            Stat::make(__('Jumlah Aset'), $totalAssets)->color('primary'),
            Stat::make(__('Nilai Aset Aktif'), 'IDR '.number_format($activeAssetValue))->color('primary'),
            Stat::make(__('Nilai Aset Dijual'), 'IDR '.number_format($soldAssetValue))->color('gray'),
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\AssetLocation;
use App\Models\BusinessEntity;
use App\Support\AssetReconciliationNormalizer as Normalizer;
use Illuminate\Console\Command;

class ValidateVehicleAuditMastersCommand extends Command
{
    protected $signature = 'vehicle-audit:validate-masters';

    protected $description = 'Verify vehicle-audit alias targets resolve to exact business_entities / asset_locations masters';

    public function handle(): int
    {
        $entityFailures = $this->missingBusinessEntities();
        $locationFailures = $this->missingLocations();

        if ($entityFailures === [] && $locationFailures === []) {
            $this->info('Vehicle audit masters OK: all alias targets resolve.');

            return self::SUCCESS;
        }

        foreach ($entityFailures as $failure) {
            $this->error($failure);
        }

        foreach ($locationFailures as $failure) {
            $this->error($failure);
        }

        $this->newLine();
        $this->warn('Fix master names or config/vehicle-asset-reconciliation.php aliases, then re-run.');

        return self::FAILURE;
    }

    /** @return list<string> */
    private function missingBusinessEntities(): array
    {
        $targets = collect(config('vehicle-asset-reconciliation.business_entity_aliases', []))
            ->values()
            ->filter()
            ->unique()
            ->values();

        $names = BusinessEntity::query()->pluck('name');
        $failures = [];

        foreach ($targets as $target) {
            $match = $names->first(fn (string $name): bool => strcasecmp($name, (string) $target) === 0);
            if ($match === null) {
                $failures[] = "Missing business_entities.name exact: {$target}";
            }
        }

        return $failures;
    }

    /** @return list<string> */
    private function missingLocations(): array
    {
        $targets = collect(config('vehicle-asset-reconciliation.location_aliases', []))
            ->values()
            ->filter()
            ->unique()
            ->values();

        $locations = AssetLocation::query()->get(['name', 'external_code']);
        $failures = [];

        foreach ($targets as $target) {
            $targetKey = Normalizer::key((string) $target);
            $match = $locations->first(function (AssetLocation $location) use ($target, $targetKey): bool {
                return strcasecmp((string) $location->name, (string) $target) === 0
                    || Normalizer::key($location->name) === $targetKey
                    || Normalizer::key($location->external_code) === $targetKey;
            });

            if ($match === null) {
                $failures[] = "Missing asset_locations.name (or matching external_code) for alias target: {$target}";
            }
        }

        return $failures;
    }
}

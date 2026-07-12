<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetRequestResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Widgets\CustomAssetWidget;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VehicleChecksheetResource;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FilamentShieldIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_shield_preserves_permission_keys_used_by_existing_roles_and_policies(): void
    {
        $rolePermissions = FilamentShield::getResourcePolicyActionsWithPermissions(RoleResource::class);
        $assetRequestPermissions = FilamentShield::getResourcePolicyActionsWithPermissions(AssetRequestResource::class);
        $widget = FilamentShield::getWidgets()[CustomAssetWidget::class];

        $this->assertSame('view_any_role', $rolePermissions['viewAny']);
        $this->assertSame('view_any_asset::request', $assetRequestPermissions['viewAny']);
        $this->assertSame('widget_CustomAssetWidget', array_key_first($widget['permissions']));
    }

    public function test_v4_config_manages_each_resource_specific_permission(): void
    {
        $this->assertResourcePermissions(AssetResource::class, [
            'export' => 'export_asset',
            'import' => 'import_asset',
        ]);
        $this->assertResourcePermissions(AssetRequestResource::class, [
            'export' => 'export_asset::request',
        ]);
        $this->assertResourcePermissions(UserResource::class, [
            'import' => 'import_user',
        ]);
        $this->assertResourcePermissions(VehicleChecksheetResource::class, [
            'export' => 'export_vehicle::checksheet',
            'import' => 'import_vehicle::checksheet',
        ]);
    }

    public function test_resources_no_longer_use_the_contract_ignored_by_shield_v4(): void
    {
        foreach ([
            AssetResource::class,
            AssetRequestResource::class,
            UserResource::class,
            VehicleChecksheetResource::class,
        ] as $resource) {
            $this->assertFalse(
                is_subclass_of($resource, HasShieldPermissions::class),
                "{$resource} still implements the deprecated Shield v3 contract.",
            );
        }
    }

    public function test_generated_policies_do_not_contain_unresolved_permission_placeholders(): void
    {
        $policyFiles = glob(app_path('Policies/*Policy.php')) ?: [];

        foreach ($policyFiles as $policyFile) {
            $this->assertStringNotContainsString(
                "->can('{{",
                file_get_contents($policyFile),
                basename($policyFile).' contains an unresolved Filament Shield placeholder.',
            );
        }
    }

    public function test_local_policy_stubs_use_the_shield_v4_dynamic_method_format(): void
    {
        $stubPath = base_path('stubs/filament-shield');

        $this->assertFileExists($stubPath.'/AuthenticatablePolicy.stub');
        $this->assertStringContainsString('{{ methods }}', file_get_contents($stubPath.'/AuthenticatablePolicy.stub'));
        $this->assertStringContainsString('{{ methods }}', file_get_contents($stubPath.'/DefaultPolicy.stub'));
    }

    public function test_database_seeder_selects_the_admin_panel_for_non_interactive_shield_commands(): void
    {
        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        preg_match_all("/Artisan::call\('shield:[^']+', \[(.*?)\]\);/s", $seeder, $calls);

        $this->assertCount(2, $calls[1], 'Expected both Shield calls to be covered.');

        foreach ($calls[1] as $arguments) {
            $this->assertStringContainsString("'--panel' => 'admin'", $arguments);
        }
    }

    public function test_shielded_asset_widget_is_hidden_for_guests_without_throwing(): void
    {
        Auth::logout();

        $this->assertFalse(CustomAssetWidget::canView());
    }

    /**
     * @param  class-string  $resource
     * @param  array<string, string>  $expected
     */
    private function assertResourcePermissions(string $resource, array $expected): void
    {
        $permissions = FilamentShield::getResourcePolicyActionsWithPermissions($resource);

        foreach ($expected as $action => $permission) {
            $this->assertSame($permission, $permissions[$action] ?? null);
        }
    }
}

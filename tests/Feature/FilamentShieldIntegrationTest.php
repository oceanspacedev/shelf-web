<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetRequestResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Widgets\CustomAssetWidget;
use App\Filament\Resources\DivisionResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VehicleChecksheetResource;
use App\Models\User;
use BezhanSalleh\FilamentShield\Commands\GenerateCommand;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use TomatoPHP\FilamentSettingsHub\Pages\SettingsHub;

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
        $settingsPage = FilamentShield::getPages()[SettingsHub::class];

        $this->assertSame('view_any_role', $rolePermissions['viewAny']);
        $this->assertSame('view_any_asset::request', $assetRequestPermissions['viewAny']);
        $this->assertSame('widget_CustomAssetWidget', array_key_first($widget['permissions']));
        $this->assertSame('View:SettingsHub', array_key_first($settingsPage['permissions']));
    }

    public function test_v4_config_manages_each_resource_specific_permission(): void
    {
        $singleParameterMethods = config('filament-shield.policies.single_parameter_methods');

        $this->assertContains('export', $singleParameterMethods);
        $this->assertContains('import', $singleParameterMethods);

        $generator = new ReflectionMethod(GenerateCommand::class, 'generatePolicyStubVariables');
        $assetStubVariables = $generator->invoke(
            app(GenerateCommand::class),
            FilamentShield::getResources()[AssetResource::class],
        );

        $this->assertSame('SingleParamMethod', $assetStubVariables['export']['stub']);
        $this->assertSame('SingleParamMethod', $assetStubVariables['import']['stub']);

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

    public function test_shield_only_generates_actions_exposed_by_each_resource(): void
    {
        $assetPermissions = FilamentShield::getResourcePolicyActionsWithPermissions(AssetResource::class);
        $assetRequestPermissions = FilamentShield::getResourcePolicyActionsWithPermissions(AssetRequestResource::class);
        $divisionPermissions = FilamentShield::getResourcePolicyActionsWithPermissions(DivisionResource::class);

        foreach (['restore', 'restoreAny', 'forceDelete', 'forceDeleteAny'] as $action) {
            $this->assertArrayHasKey($action, $assetRequestPermissions);
            $this->assertArrayHasKey($action, $divisionPermissions);
        }

        foreach ([$assetPermissions, $assetRequestPermissions, $divisionPermissions] as $permissions) {
            $this->assertArrayNotHasKey('replicate', $permissions);
            $this->assertArrayNotHasKey('reorder', $permissions);
        }
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

    public function test_every_generated_resource_permission_matches_its_policy_method(): void
    {
        foreach (FilamentShield::getResources() as $resource) {
            $policy = Gate::getPolicyFor($resource['modelFqcn']);

            $this->assertNotNull($policy, "No policy is registered for {$resource['modelFqcn']}.");

            foreach (FilamentShield::getResourcePolicyActionsWithPermissions($resource['resourceFqcn']) as $action => $permission) {
                $method = new ReflectionMethod($policy, $action);
                $user = Mockery::mock(User::class);
                $user->shouldReceive('can')->once()->with($permission)->andReturnTrue();
                $arguments = [$user];

                if ($method->getNumberOfParameters() > 1) {
                    $modelClass = $resource['modelFqcn'];
                    $arguments[] = new $modelClass;
                }

                $this->assertTrue(
                    $method->invokeArgs($policy, $arguments),
                    sprintf('%s::%s does not check the permission generated by Shield.', $policy::class, $action),
                );
            }
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

        preg_match_all("/\\\$this->runShieldCommand\('shield:[^']+', \[(.*?)\]\);/s", $seeder, $calls);

        $this->assertCount(2, $calls[1], 'Expected both Shield calls to be covered.');

        foreach ($calls[1] as $arguments) {
            $this->assertStringContainsString("'--panel' => 'admin'", $arguments);
            $this->assertStringContainsString("'--no-interaction' => true", $arguments);
        }

        $this->assertStringContainsString("'--option' => 'policies_and_permissions'", $calls[1][1]);
        $this->assertStringContainsString('if (Artisan::call($command, $arguments) !== 0)', $seeder);
        $this->assertStringContainsString('throw new RuntimeException', $seeder);
    }

    public function test_database_seeder_stops_when_a_shield_command_fails(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('shield:generate', ['--panel' => 'admin'])
            ->andReturn(1);

        $method = new ReflectionMethod(DatabaseSeeder::class, 'runShieldCommand');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shield command [shield:generate] failed.');

        $method->invoke(new DatabaseSeeder, 'shield:generate', ['--panel' => 'admin']);
    }

    public function test_shielded_asset_widget_uses_the_parent_fallback_for_guests_without_throwing(): void
    {
        Auth::logout();

        $this->assertTrue(CustomAssetWidget::canView());
    }

    public function test_shielded_asset_widget_honors_the_authenticated_users_permission(): void
    {
        $deniedUser = Mockery::mock(User::class);
        $deniedUser->shouldReceive('can')->once()->with('widget_CustomAssetWidget')->andReturnFalse();
        Filament::auth()->setUser($deniedUser);

        $this->assertFalse(CustomAssetWidget::canView());

        $allowedUser = Mockery::mock(User::class);
        $allowedUser->shouldReceive('can')->once()->with('widget_CustomAssetWidget')->andReturnTrue();
        Filament::auth()->setUser($allowedUser);

        $this->assertTrue(CustomAssetWidget::canView());
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

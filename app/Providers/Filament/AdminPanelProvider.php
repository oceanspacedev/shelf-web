<?php

namespace App\Providers\Filament;

use Apriansyahrs\MekayaTheme\Livewire\MekayaSidebar;
use Apriansyahrs\MekayaTheme\MekayaPlugin;
use App\Filament\Pages\Auth\Login;
use App\Filament\Plugins\FilamentExceptionsPlugin;
use App\Filament\Resources\AssetResource\Widgets\CustomAssetWidget;
use App\Support\ObservabilityAccess;
use Asmit\ResizedColumn\ResizedColumnPlugin;
use Awcodes\Overlook\OverlookPlugin;
use Awcodes\Overlook\Widgets\OverlookWidget;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use TomatoPHP\FilamentPWA\FilamentPWAPlugin;
use TomatoPHP\FilamentSettingsHub\FilamentSettingsHubPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(
                MekayaPlugin::make()
                    ->colors(['primary' => Color::Blue]),
            )
            ->sidebarLivewireComponent(MekayaSidebar::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->passwordReset(null, null)
            ->profile(null)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->navigationItems(ObservabilityAccess::filamentNavigationItems())
            ->widgets([
                Widgets\AccountWidget::class,
                OverlookWidget::class,
                CustomAssetWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->databaseNotifications(isLazy: false)
            ->databaseNotificationsPolling('2s')
            ->plugins([
                ResizedColumnPlugin::make(),
                FilamentPWAPlugin::make(),
                FilamentSettingsHubPlugin::make()
                    ->allowSiteSettings()
                    ->allowSocialMenuSettings()
                    ->allowShield(),
                FilamentExceptionsPlugin::make(),
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 2,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
                OverlookPlugin::make()
                    ->sort(2)
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'md' => 3,
                        'lg' => 4,
                        'xl' => 4,
                        '2xl' => null,
                    ]),
            ])
            ->maxContentWidth(Width::Full);
    }
}

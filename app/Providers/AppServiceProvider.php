<?php

namespace App\Providers;

use App\Enums\BadgeColor;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Facades\FilamentColor;
use Filament\Widgets\Widget;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureFilamentShield();
        $this->configureRateLimiting();
        $this->registerFilamentBadgeColors();

        View::prependNamespace('filament-panels', resource_path('views/vendor/filament-panels'));

        // Preserve Filament v3 layout behavior: Section/Grid/Fieldset span all columns.
        Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset->columnSpanFull());
        Grid::configureUsing(fn (Grid $grid) => $grid->columnSpanFull());
        Section::configureUsing(fn (Section $section) => $section->columnSpanFull());

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['id', 'en'])
                ->circular()
                ->renderHook('panels::user-menu.before');
        });
    }

    /**
     * Keep permissions attached to roles created before the Shield v4 upgrade.
     *
     * Settings Hub pages already authorize Shield v4's `View:*` keys, while
     * application resources and widgets still have persisted Shield v3 keys.
     */
    protected function configureFilamentShield(): void
    {
        FilamentShield::buildPermissionKeyUsing(
            function (string $entity, string $affix, string $subject, string $case, string $separator): string {
                if (is_subclass_of($entity, Resource::class)) {
                    return Str::of($affix)
                        ->snake()
                        ->append('_')
                        ->append(
                            Str::of($entity)
                                ->afterLast('\\')
                                ->beforeLast('Resource')
                                ->snake()
                                ->replace('_', '::')
                        )
                        ->toString();
                }

                if (is_subclass_of($entity, Widget::class)) {
                    return Str::of('widget_')
                        ->append(class_basename($entity))
                        ->toString();
                }

                return FilamentShield::defaultPermissionKeyBuilder(
                    affix: $affix,
                    separator: $separator,
                    subject: $subject,
                    case: $case,
                );
            }
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter untuk endpoint publik pengajuan aset (tanpa auth).
        // Membatasi spam + memperkecil window race condition reference_number.
        RateLimiter::for('public', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($request->ip());
        });
    }

    /**
     * Warna BadgeColor (amber, pink, rose, …) dipakai di badge entitas bisnis.
     * Harus didaftarkan ke Filament agar CSS variable warna ter-generate.
     */
    protected function registerFilamentBadgeColors(): void
    {
        FilamentColor::register(
            collect(BadgeColor::cases())
                ->mapWithKeys(fn (BadgeColor $color) => [$color->value => $color->getColor()])
                ->all()
        );
    }
}

<?php

namespace App\Support;

use App\Models\User;
use Filament\Navigation\NavigationItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

final class ObservabilityAccess
{
    /**
     * Shield custom permissions (config/filament-shield.php → custom_permissions).
     */
    public const VIEW_HORIZON = 'View:Horizon';

    public const VIEW_LOG_VIEWER = 'View:LogViewer';

    public const DOWNLOAD_LOGS = 'Download:LogViewer';

    public const DELETE_LOGS = 'Delete:LogViewer';

    public const VIEW_PULSE = 'View:Pulse';

    /**
     * @return array<string, string>
     */
    public static function shieldPermissions(): array
    {
        return [
            self::VIEW_HORIZON => 'View Horizon',
            self::VIEW_LOG_VIEWER => 'View Log Viewer',
            self::DOWNLOAD_LOGS => 'Download Log Viewer Files',
            self::DELETE_LOGS => 'Delete Log Viewer Files',
            self::VIEW_PULSE => 'View Pulse',
        ];
    }

    /**
     * Whether the user can open at least one observability tool.
     */
    public static function allowed(?Authenticatable $user): bool
    {
        return self::canViewHorizon($user) || self::canViewLogViewer($user);
    }

    public static function canViewHorizon(?Authenticatable $user): bool
    {
        return self::granted($user, self::VIEW_HORIZON);
    }

    public static function canViewLogViewer(?Authenticatable $user): bool
    {
        return self::granted($user, self::VIEW_LOG_VIEWER);
    }

    public static function canViewPulse(?Authenticatable $user): bool
    {
        return self::granted($user, self::VIEW_PULSE);
    }

    public static function canDownloadLogs(?Authenticatable $user): bool
    {
        return self::canViewLogViewer($user) && self::granted($user, self::DOWNLOAD_LOGS);
    }

    public static function canDeleteLogs(?Authenticatable $user): bool
    {
        return self::canViewLogViewer($user) && self::granted($user, self::DELETE_LOGS);
    }

    public static function register(): void
    {
        $user = fn (mixed $user): ?Authenticatable => $user instanceof Authenticatable ? $user : null;

        Gate::define('viewHorizon', fn ($actor = null): bool => self::canViewHorizon($user($actor)));
        Gate::define('viewLogViewer', fn ($actor = null): bool => self::canViewLogViewer($user($actor)));
        Gate::define('downloadLogFile', fn ($actor = null): bool => self::canDownloadLogs($user($actor)));
        Gate::define('downloadLogFolder', fn ($actor = null): bool => self::canDownloadLogs($user($actor)));
        Gate::define('deleteLogFile', fn ($actor = null): bool => self::canDeleteLogs($user($actor)));
        Gate::define('deleteLogFolder', fn ($actor = null): bool => self::canDeleteLogs($user($actor)));
        Gate::define('viewPulse', fn ($actor = null): bool => self::canViewPulse($user($actor)));
    }

    /**
     * @return list<NavigationItem>
     */
    public static function filamentNavigationItems(): array
    {
        return [
            NavigationItem::make('Horizon')
                ->url(url('/'.trim((string) config('horizon.path', 'horizon'), '/')), shouldOpenInNewTab: true)
                ->icon('heroicon-o-queue-list')
                ->visible(fn (): bool => self::canViewHorizon(auth()->user())),
            NavigationItem::make('Log Viewer')
                ->url(url('/'.trim((string) config('log-viewer.route_path', 'log-viewer'), '/')), shouldOpenInNewTab: true)
                ->icon('heroicon-o-document-magnifying-glass')
                ->visible(fn (): bool => self::canViewLogViewer(auth()->user())),
        ];
    }

    private static function granted(?Authenticatable $user, string $permission): bool
    {
        return $user instanceof User && $user->checkPermissionTo($permission);
    }
}

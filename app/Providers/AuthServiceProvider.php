<?php

namespace App\Providers;

use App\Models\AssetRequest;
use App\Models\AssetTransfer;
use App\Models\Task;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\AssetRequestPolicy;
use App\Policies\AssetTransferPolicy;
use App\Policies\ExceptionPolicy;
use App\Policies\RolePolicy;
use App\Policies\TaskPolicy;
use BezhanSalleh\FilamentExceptions\Models\Exception;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        AssetRequest::class => AssetRequestPolicy::class,
        AssetTransfer::class => AssetTransferPolicy::class,
        Activity::class => ActivityPolicy::class,
        Exception::class => ExceptionPolicy::class,
        Role::class => RolePolicy::class,
        Task::class => TaskPolicy::class,
        \App\Models\ObChecksheet::class => \App\Policies\ObChecksheetPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('viewPulse', function (User $user) {
            return $user->hasRole('super_admin');
        });
    }
}

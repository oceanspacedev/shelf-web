<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Models\ApprovalLevel;
use App\Models\PublicAssetRequest;
use App\Policies\ActivityPolicy;
use App\Policies\ApprovalLevelPolicy;
use App\Policies\ExceptionPolicy;
use App\Policies\PublicAssetRequestPolicy;
use App\Policies\RolePolicy;
use BezhanSalleh\FilamentExceptions\Models\Exception;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\User;
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
        ApprovalLevel::class => ApprovalLevelPolicy::class,
        Activity::class => ActivityPolicy::class,
        Exception::class => ExceptionPolicy::class,
        PublicAssetRequest::class => PublicAssetRequestPolicy::class,
        Role::class => RolePolicy::class,
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

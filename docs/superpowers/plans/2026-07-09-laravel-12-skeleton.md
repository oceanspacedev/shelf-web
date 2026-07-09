# Laravel 12 Skeleton Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the app bootstrap to the official Laravel 12 skeleton while preserving schedules, rate limiters, policies, Filament exception reporting, and Filament auth redirects.

**Architecture:** Replace legacy Kernel/Handler/RouteServiceProvider with `Application::configure` in `bootstrap/app.php` plus `bootstrap/providers.php`. Move rate limiters into `AppServiceProvider`, schedules/exceptions/middleware redirects into `bootstrap/app.php`, and delete obsolete skeleton files.

**Tech Stack:** Laravel 12.63, Filament 4.11, PHPUnit 11

**Spec:** `docs/superpowers/specs/2026-07-09-laravel-12-skeleton-design.md`

---

### Task 1: Bootstrap + providers

**Files:**
- Create: `bootstrap/providers.php`
- Modify: `bootstrap/app.php`
- Modify: `public/index.php`
- Modify: `artisan`
- Modify: `tests/TestCase.php`
- Delete: `tests/CreatesApplication.php`

- [ ] **Step 1: Create `bootstrap/providers.php`**

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
];
```

- [ ] **Step 2: Replace `bootstrap/app.php`**

```php
<?php

use BezhanSalleh\FilamentExceptions\Facades\FilamentExceptions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'signed' => \App\Http\Middleware\ValidateSignature::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => route('filament.admin.auth.login'));
        $middleware->redirectUsersTo('/admin');
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('notifications:send-scheduled')->dailyAt('13:20');
        $schedule->command('model:prune')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $e): void {
            FilamentExceptions::report($e);
        });
    })->create();
```

- [ ] **Step 3: Update entrypoints to Laravel 12 style**

`public/index.php` and `artisan` should use `$app->handleRequest()` / `$app->handleCommand()`.

`tests/TestCase.php` should drop `CreatesApplication` (framework base TestCase already bootstraps `bootstrap/app.php`). Delete `tests/CreatesApplication.php`.

- [ ] **Step 4: Smoke-check artisan still boots after later tasks** (deferred to Task 3 verification)

---

### Task 2: AppServiceProvider, middleware, config cleanup, delete legacy

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Http/Middleware/RedirectIfAuthenticated.php`
- Modify: `config/app.php`
- Modify: `routes/web.php` / `routes/api.php` comments (optional)
- Delete: `app/Http/Kernel.php`
- Delete: `app/Console/Kernel.php`
- Delete: `app/Console/ContainerCommandLoader.php`
- Delete: `app/Exceptions/Handler.php`
- Delete: `app/Providers/RouteServiceProvider.php`

- [ ] **Step 1: Move rate limiters into `AppServiceProvider::boot()`**

Register `api` and `public` limiters exactly as in old `RouteServiceProvider`.

- [ ] **Step 2: Update `RedirectIfAuthenticated` to redirect to `/admin`**

Remove `RouteServiceProvider` dependency.

- [ ] **Step 3: Clean `config/app.php`**

Remove `providers` and `aliases` arrays and related imports (`ServiceProvider`, `Facade`, app provider imports). Keep existing env/timezone/locale/maintenance settings.

- [ ] **Step 4: Delete legacy skeleton files listed above**

---

### Task 3: Verify

- [ ] **Step 1: Run**

```bash
php artisan about
php artisan list
php artisan schedule:list
php artisan route:list --path=asset-requests
php artisan test
```

Expected: artisan boots; schedule shows both tasks; public routes present; tests pass.

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "refactor: align application skeleton with Laravel 12"
```

---

## Spec coverage

| Spec requirement | Task |
|------------------|------|
| `bootstrap/app.php` Application builder | Task 1 |
| `bootstrap/providers.php` | Task 1 |
| Entrypoints + tests bootstrap | Task 1 |
| Rate limiters in AppServiceProvider | Task 2 |
| Guest redirect `/admin` | Task 1 + 2 |
| FilamentExceptions reporting | Task 1 |
| Schedule migration | Task 1 |
| Remove Kernels/Handler/RouteServiceProvider/ContainerCommandLoader | Task 2 |
| Verification checklist | Task 3 |

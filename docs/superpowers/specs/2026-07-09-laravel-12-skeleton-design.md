# Design: Full Laravel 12 Skeleton Alignment

**Date:** 2026-07-09  
**Status:** Approved for planning  
**Scope:** Align application bootstrap/structure to Laravel 12 skeleton (not Filament resource patterns)

## Context

The project already runs **Laravel 12.63** and **Filament 4.11**, but the application skeleton is still largely Laravel 10-style:

- `bootstrap/app.php` still creates a classic `Application` and binds `Http\Kernel`, `Console\Kernel`, and `Exceptions\Handler`
- Providers are registered via `config/app.php` `providers` array
- Custom `ContainerCommandLoader` duplicates framework behavior
- `RouteServiceProvider` still owns routing + rate limiters
- `public/index.php` / test bootstrap still resolve the old HTTP/Console kernels

Goal: migrate to the official Laravel 12 application structure in one PR, preserving current behavior except for an intentional guest redirect change.

## Decisions (locked)

| Topic | Decision |
|-------|----------|
| Scope | Laravel 12 skeleton only (Filament resource standardization is out of scope) |
| Approach | Big-bang single PR |
| Custom `ContainerCommandLoader` | Remove; verify Artisan with framework defaults |
| Authenticated `guest` redirect | `/admin` (was `RouteServiceProvider::HOME = '/home'`) |
| EventServiceProvider | Keep (still maps `Registered` → email verification listener) |
| Business/Filament UI code | Untouched |

## Target architecture

### Entrypoints

1. **`bootstrap/app.php`** uses:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void { /* ... */ })
    ->withExceptions(function (Exceptions $exceptions): void { /* ... */ })
    ->withSchedule(function (Schedule $schedule): void { /* ... */ })
    ->create();
```

2. **`bootstrap/providers.php`** registers application providers.
3. **`public/index.php`**, **`artisan`**, and **`tests/CreatesApplication.php`** follow Laravel 12 entrypoint conventions (no manual `Http\Kernel` / `Console\Kernel` resolution).

### Providers (`bootstrap/providers.php`)

Keep:

- `App\Providers\AppServiceProvider`
- `App\Providers\AuthServiceProvider` (policies + `viewPulse` gate)
- `App\Providers\EventServiceProvider`
- `App\Providers\Filament\AdminPanelProvider`

Do not enable `BroadcastServiceProvider` (remains commented/off as today).

Remove provider registration from `config/app.php` (`providers` / legacy aliases block cleaned to Laravel 12 style).

### Middleware

Move Kernel middleware configuration into `bootstrap/app.php` `withMiddleware`:

- Preserve middleware aliases currently defined in `app/Http/Kernel.php` (`auth`, `guest`, `signed`, `throttle`, etc.)
- Keep existing app middleware subclasses that carry configuration (`TrustProxies`, `TrimStrings`, `VerifyCsrfToken`, `EncryptCookies`, `Authenticate`, `RedirectIfAuthenticated`, etc.)
- Update `RedirectIfAuthenticated` so it no longer depends on `RouteServiceProvider::HOME`; redirect to `/admin`
- Configure unauthenticated redirect to Filament admin login (same behavior as current `Authenticate` middleware)

### Rate limiting

Move from `RouteServiceProvider` into `AppServiceProvider::boot()`:

- `api`: 60/minute by user id or IP
- `public`: 5/minute by IP; `Limit::none()` in `testing`

### Schedule

Move into `bootstrap/app.php` `withSchedule` (single source of truth):

- `notifications:send-scheduled` daily at `13:20`
- `model:prune` daily

Note: today `model:prune` lives in `Http\Kernel::schedule()` (misplaced) and notifications live in `Console\Kernel::schedule()`. Both move to the new schedule callback.

### Exception reporting

Replace `app/Exceptions/Handler.php` with `withExceptions` in `bootstrap/app.php`:

- Keep `dontFlash` equivalents if needed via framework defaults / explicit config
- Preserve Filament Exceptions reporting:

```php
$exceptions->reportable(function (Throwable $e): void {
    \BezhanSalleh\FilamentExceptions\Facades\FilamentExceptions::report($e);
});
```

Laravel only invokes `reportable` callbacks for exceptions that pass `shouldReport()`, so this matches the current Handler guard without re-implementing it.

## Files to add/change/remove

### Add

- `bootstrap/providers.php`

### Change

- `bootstrap/app.php` → Laravel 12 Application builder
- `public/index.php` → Laravel 12 request handling
- `artisan` → Laravel 12 console handling (if still old-style)
- `tests/CreatesApplication.php` → Laravel 12 bootstrap
- `config/app.php` → remove legacy providers/aliases registration style
- `app/Providers/AppServiceProvider.php` → register rate limiters
- `app/Http/Middleware/RedirectIfAuthenticated.php` → `/admin` redirect
- `routes/web.php` / `routes/api.php` comments only if needed (behavior unchanged)

### Remove

- `app/Http/Kernel.php`
- `app/Console/Kernel.php`
- `app/Console/ContainerCommandLoader.php`
- `app/Exceptions/Handler.php`
- `app/Providers/RouteServiceProvider.php`

## Data flow (request / console)

```text
HTTP:
  public/index.php
    -> bootstrap/app.php (Application::configure)
    -> framework HTTP kernel (internal)
    -> middleware stack from withMiddleware
    -> routes/web.php | routes/api.php
    -> controllers / Filament panel

Console:
  artisan
    -> bootstrap/app.php
    -> framework console kernel (internal)
    -> routes/console.php + discovered app/Console/Commands
    -> schedule from withSchedule
```

## Error handling

- Framework default exception rendering remains
- Reportable exceptions continue to Filament Exceptions
- No change to user-facing error pages in this PR

## Testing / verification checklist

Must pass after implementation:

1. `php artisan about` works
2. `php artisan list` works without custom command loader
3. `php artisan notifications:send-scheduled` resolves and runs
4. `php artisan schedule:list` shows both scheduled tasks
5. Public `asset-requests` routes work; `throttle:public` still applied
6. Auth-protected PDF download routes still require login
7. Filament `/admin` login works; `guest` users redirect to `/admin`
8. Pulse `viewPulse` gate still restricted to `super_admin`
9. Existing automated tests pass (`php artisan test`)

## Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Artisan commands fail without custom loader | Remove loader; verify `list` + scheduled command immediately |
| `public` rate limiter missing | Explicitly re-register in `AppServiceProvider` |
| Providers (esp. Filament panel) not loaded | Register in `bootstrap/providers.php` and smoke-test `/admin` |
| Schedule duplicated or dropped | Only define in `withSchedule`; delete both Kernels |
| Guest redirect regression | Intentional change to `/admin`; document in PR |

## Out of scope

- Filament 4 resource/schema/action pattern cleanup
- Plugin upgrades unrelated to bootstrap
- Business logic, migrations, UI/theme changes
- Enabling broadcasting

## Success criteria

The app boots and runs on Laravel 12’s standard skeleton with no legacy Kernel/Handler/RouteServiceProvider, no custom ContainerCommandLoader, preserved schedules/rate limiters/policies/Filament exception reporting, and guest redirect to `/admin`.

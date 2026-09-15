<?php

use BezhanSalleh\FilamentExceptions\Facades\FilamentExceptions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        // Trust reverse proxy TLS termination so request()->secure() and URL generation match HTTPS.
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'admin/camera-upload',
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => route('filament.admin.auth.login'));
        $middleware->redirectUsersTo('/admin');
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('notifications:send-scheduled')->dailyAt('13:20');
        $schedule->command('model:prune')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            \Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class,
        ]);

        $exceptions->reportable(function (\Throwable $e): void {
            FilamentExceptions::report($e);
        });
    })->create();

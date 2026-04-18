<?php

namespace App\Console;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use ReflectionProperty;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('notifications:send-scheduled')->dailyAt('13:20');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Get the Artisan application instance.
     *
     * Eagerly resolving commands here avoids lazy-loader edge cases where
     * Illuminate commands may run without the Laravel container set.
     */
    protected function getArtisan(): Artisan
    {
        if (is_null($this->artisan)) {
            $this->artisan = (new Artisan($this->app, $this->events, $this->app->version()))
                ->resolveCommands($this->commands);

            // Keep lazy command loading, but ensure resolved commands receive
            // the Laravel container instance before execution.
            $commandMap = (function (Artisan $artisan): array {
                $property = new ReflectionProperty($artisan, 'commandMap');
                $property->setAccessible(true);

                return $property->getValue($artisan);
            })($this->artisan);

            $this->artisan->setCommandLoader(
                new ContainerCommandLoader($this->app, $commandMap)
            );

            if ($this->symfonyDispatcher instanceof EventDispatcher) {
                $this->artisan->setDispatcher($this->symfonyDispatcher);
                $this->artisan->setSignalsToDispatchEvent();
            }
        }

        return $this->artisan;
    }
}

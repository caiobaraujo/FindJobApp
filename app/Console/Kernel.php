<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * @var list<class-string>
     */
    protected $commands = [
        Commands\CalibrateDiscovery::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('job-leads:discover-all')->everySixHours();
    }
}

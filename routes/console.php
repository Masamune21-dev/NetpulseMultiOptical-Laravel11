<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('poll:interfaces --timeout=30')->everyMinute()->withoutOverlapping(10);

// Downsample optical stats into hourly/daily rollups so long-range redaman
// history is preserved cheaply. Runs a few minutes past the hour to capture
// the just-completed hour.
Schedule::command('stats:rollup')->hourlyAt(5)->withoutOverlapping();

// Enforce tiered retention: raw kept 30d, hourly 18mo (daily kept long-term).
Schedule::command('stats:prune')->dailyAt('03:30')->withoutOverlapping();

// Detect slow optical (redaman) degradation vs a ~1-week baseline and alert
// before the link actually goes down. Runs after the daily rollup is settled.
Schedule::command('optical:degradation')->dailyAt('06:00')->withoutOverlapping();

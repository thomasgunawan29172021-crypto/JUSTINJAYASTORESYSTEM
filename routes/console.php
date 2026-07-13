<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:auto-close')->dailyAt('23:49');
Schedule::command('attendance:purge-photos')->dailyAt('01:30');
Schedule::command('leaves:expire')->dailyAt('00:15');
Schedule::command('marketplace:purge-trash')->dailyAt('02:00');
Schedule::command('sosmed:freeze-stale')->dailyAt('01:00');
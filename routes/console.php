<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('utilityflow:maintain')->dailyAt('01:15')->withoutOverlapping();
Schedule::command('utilityflow:queue-invoice-reminders')->dailyAt('08:15')->withoutOverlapping();

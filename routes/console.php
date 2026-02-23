<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Order due date SMS reminders (daily at 8:00)
Schedule::command('orders:send-due-date-reminders', ['--days' => 1])->dailyAt('08:00');

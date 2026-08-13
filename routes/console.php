<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Hourly rather than daily: ads are hidden from the public feed the moment
// they expire (see Ad::scopePublished), so this only corrects the stored
// status — but an owner should not see a dead ad under "Active" for a day.
Schedule::command('ads:expire')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Flags students 12 months after their individual verification date and
// notifies them. Without this registered the command exists but never runs, so
// student_reverify_due_at is set and then nothing acts on it.
Schedule::command('students:require-reverification')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

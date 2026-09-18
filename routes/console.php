<?php

use App\Models\Item;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sources:dispatch')->everyMinute()->withoutOverlapping();

Schedule::command('enrich:submit')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('enrich:collect')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('briefing:generate')
    ->dailyAt(config('cyber.ai.briefing_time'))
    ->timezone(config('cyber.ai.timezone'))
    ->withoutOverlapping();

Schedule::command('model:prune', ['--model' => [Item::class]])->daily();

<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('games:cleanup')->everyMinute()->withoutOverlapping();

Schedule::command('games:cleanup --purge')->dailyAt('03:15');

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

Schedule::command('appointments:send-confirmation-emails')->dailyAt('08:00');
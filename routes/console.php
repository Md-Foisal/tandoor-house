<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:remind')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bookings:release-no-shows')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bookings:close-lapsed-holds')->everyFiveMinutes()->withoutOverlapping();

// Card orders never paid for would otherwise hold stock and kitchen slots indefinitely.
Schedule::command('orders:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('baskets:remind')->everyFifteenMinutes()->withoutOverlapping();

// Pinned to London so "close of day" and "Monday morning" follow the restaurant, not the server.
Schedule::command('reports:daily-close')->dailyAt('23:30')->timezone('Europe/London');
Schedule::command('reports:weekly')->weeklyOn(1, '07:00')->timezone('Europe/London');

Schedule::command('queue:prune-failed --hours=168')->daily();

// Activity logs (12 months), abandoned baskets and dead staff invites (30 days). Orders, payments and bookings are kept six years.
Schedule::command('model:prune')->daily()->withoutOverlapping();

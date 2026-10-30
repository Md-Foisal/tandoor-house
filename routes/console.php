<?php

use Illuminate\Support\Facades\Schedule;

// Bookings: reminders two hours ahead, no-shows released, lapsed deposit holds closed.
Schedule::command('bookings:remind')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bookings:release-no-shows')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bookings:close-lapsed-holds')->everyFiveMinutes()->withoutOverlapping();

// Orders: card orders never paid for are closed so they stop holding stock and slots.
Schedule::command('orders:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();

// Guests: one reminder for a basket left for two hours.
Schedule::command('baskets:remind')->everyFifteenMinutes()->withoutOverlapping();

// The owner's numbers.
Schedule::command('reports:daily-close')->dailyAt('23:30')->timezone('Europe/London');
Schedule::command('reports:weekly')->weeklyOn(1, '07:00')->timezone('Europe/London');

// Housekeeping.
Schedule::command('queue:prune-failed --hours=168')->daily();

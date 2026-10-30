<?php

use App\Models\ActivityLog;
use App\Models\Cart;
use App\Models\StaffInvite;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

function log_at(string $when): ActivityLog
{
    $log = ActivityLog::create(['event' => 'test', 'description' => 'x']);
    ActivityLog::whereKey($log->id)->update(['created_at' => $when]);

    return $log;
}

it('prunes activity logs older than twelve months only', function () {
    $old = log_at(now()->subMonths(12)->subDay()->toDateTimeString());
    $recent = log_at(now()->subMonths(11)->toDateTimeString());

    Artisan::call('model:prune', ['--model' => [ActivityLog::class]]);

    expect(ActivityLog::find($old->id))->toBeNull()
        ->and(ActivityLog::find($recent->id))->not->toBeNull();
});

it('prunes baskets untouched for thirty days', function () {
    $stale = Cart::create(['session_id' => 'a', 'lines' => []]);
    $stale->forceFill(['updated_at' => now()->subDays(31)->toDateTimeString()])->save();
    $fresh = Cart::create(['session_id' => 'b', 'lines' => []]);
    $fresh->forceFill(['updated_at' => now()->subDays(29)->toDateTimeString()])->save();

    Artisan::call('model:prune', ['--model' => [Cart::class]]);

    expect(Cart::find($stale->id))->toBeNull()
        ->and(Cart::find($fresh->id))->not->toBeNull();
});

it('prunes accepted and expired staff invites after thirty days but keeps usable ones', function () {
    $make = fn (array $attrs) => StaffInvite::create($attrs + [
        'user_id' => User::factory()->create()->id,
        'token_hash' => hash('sha256', fake()->unique()->uuid()),
        'sent_at' => now()->subDays(60),
        'expires_at' => now()->addDay(),
    ]);

    $expiredLongAgo = $make(['expires_at' => now()->subDays(31)]);
    $acceptedLongAgo = $make(['accepted_at' => now()->subDays(31)]);
    $expiredRecently = $make(['expires_at' => now()->subDays(5)]);
    $usable = $make([]);

    Artisan::call('model:prune', ['--model' => [StaffInvite::class]]);

    expect(StaffInvite::find($expiredLongAgo->id))->toBeNull()
        ->and(StaffInvite::find($acceptedLongAgo->id))->toBeNull()
        ->and(StaffInvite::find($expiredRecently->id))->not->toBeNull()
        ->and(StaffInvite::find($usable->id))->not->toBeNull();
});

it('prunes as time passes', function () {
    $log = ActivityLog::create(['event' => 'test', 'description' => 'x']);

    $this->travel(13)->months();
    Artisan::call('model:prune', ['--model' => [ActivityLog::class]]);

    expect(ActivityLog::find($log->id))->toBeNull();
});

it('schedules model:prune daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command, 'model:prune'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 0 * * *');
});

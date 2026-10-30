<?php

use App\Actions\Team\RecordTipAllocations;
use App\Actions\Team\WeekRange;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\TipAllocation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

// Wednesday 28 October 2026; the week starts Monday the 26th.
beforeEach(function () {
    $this->travelTo('2026-10-28 18:00:00');
    $this->week = WeekRange::from('2026-10-26');
    $this->manager = User::factory()->role(Role::Manager)->create();
    $this->anna = User::factory()->role(Role::Waiter)->create();
    $this->ben = User::factory()->role(Role::Waiter)->create();
    $this->cara = User::factory()->role(Role::Kitchen)->create();

    foreach ([[$this->anna, 6], [$this->ben, 3], [$this->cara, 3]] as [$user, $hours]) {
        Attendance::create(['user_id' => $user->id, 'clocked_in_at' => '2026-10-27 12:00', 'clocked_out_at' => now()->parse('2026-10-27 12:00')->addHours($hours)]);
    }

    Order::factory()->completed()->withTip(500)->create(['completed_at' => '2026-10-27 20:00']);
    Order::factory()->completed()->withTip(251)->create(['completed_at' => '2026-10-28 13:00']);
    Order::factory()->completed()->withTip(900)->create(['completed_at' => '2026-10-20 13:00']);
});

it('records the split with shares that add up to the week tips', function () {
    $rows = app(RecordTipAllocations::class)($this->manager, $this->week);

    expect($rows)->toHaveCount(3)
        ->and($rows->sum('amount'))->toBe(751)
        ->and(TipAllocation::where('period', '2026-10-26')->sum('amount'))->toBe(751)
        ->and(TipAllocation::where('user_id', $this->anna->id)->value('minutes_worked'))->toBe(360)
        ->and(TipAllocation::where('user_id', $this->anna->id)->value('amount'))->toBe(375)
        ->and(TipAllocation::first()->recorded_by)->toBe($this->manager->id)
        ->and(ActivityLog::where('event', 'tips.recorded')->count())->toBe(1);
});

it('replaces the week when recorded again', function () {
    app(RecordTipAllocations::class)($this->manager, $this->week);

    Order::factory()->completed()->withTip(1000)->create(['completed_at' => '2026-10-28 14:00']);
    app(RecordTipAllocations::class)($this->manager, $this->week);

    expect(TipAllocation::count())->toBe(3)
        ->and((int) TipAllocation::sum('amount'))->toBe(1751);
});

it('leaves other weeks alone', function () {
    $other = TipAllocation::factory()->create(['period' => '2026-10-19', 'user_id' => $this->anna->id]);

    app(RecordTipAllocations::class)($this->manager, $this->week);

    expect($other->fresh())->not->toBeNull()->and(TipAllocation::count())->toBe(4);
});

it('refuses to change a week that is marked paid', function () {
    app(RecordTipAllocations::class)($this->manager, $this->week);
    TipAllocation::where('user_id', $this->anna->id)->update(['paid_at' => now()]);

    expect(fn () => app(RecordTipAllocations::class)($this->manager, $this->week))->toThrow(ValidationException::class)
        ->and(TipAllocation::count())->toBe(3);
});

it('records nothing for a week nobody worked', function () {
    $rows = app(RecordTipAllocations::class)($this->manager, WeekRange::from('2026-09-07'));

    expect($rows)->toHaveCount(0)->and(TipAllocation::count())->toBe(0);
});

it('relates allocations to users', function () {
    app(RecordTipAllocations::class)($this->manager, $this->week);

    expect($this->anna->tipAllocations)->toHaveCount(1);
});

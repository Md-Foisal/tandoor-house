<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\TipAllocation;
use App\Models\User;

// Wednesday 4 November 2026: the week of Monday 26 October is over, the current one is not.
beforeEach(function () {
    $this->travelTo('2026-11-04 10:00:00');
    $this->anna = User::factory()->role(Role::Waiter)->create();
    $this->ben = User::factory()->role(Role::Kitchen)->create();

    foreach ([[$this->anna, 6], [$this->ben, 3]] as [$user, $hours]) {
        Attendance::create(['user_id' => $user->id, 'clocked_in_at' => '2026-10-27 12:00', 'clocked_out_at' => now()->parse('2026-10-27 12:00')->addHours($hours)]);
    }

    Order::factory()->completed()->withTip(900)->create(['completed_at' => '2026-10-28 20:00']);
});

function tipsUser(Role $role): User
{
    return User::factory()->role($role)->create();
}

it('lets managers and owners record a finished week', function (Role $role) {
    $this->actingAs(tipsUser($role))->post('/staff/team/tips/record', ['week' => '2026-10-26'])
        ->assertSessionHasNoErrors();

    expect(TipAllocation::where('period', '2026-10-26')->sum('amount'))->toBe(900)
        ->and(TipAllocation::where('user_id', $this->anna->id)->value('amount'))->toBe(600)
        ->and(ActivityLog::where('event', 'tips.recorded')->count())->toBe(1);
})->with([Role::Manager, Role::Owner]);

it('keeps recording behind the team area', function (Role $role) {
    $this->actingAs(tipsUser($role));
    $this->post('/staff/team/tips/record', ['week' => '2026-10-26'])->assertForbidden();
    $this->post('/staff/team/tips/pay', ['week' => '2026-10-26'])->assertForbidden();

    expect(TipAllocation::count())->toBe(0);
})->with([Role::Cashier, Role::Waiter, Role::Kitchen, Role::Rider, Role::Customer]);

it('refuses to record a week that has not ended', function () {
    Attendance::create(['user_id' => $this->anna->id, 'clocked_in_at' => '2026-11-03 12:00', 'clocked_out_at' => '2026-11-03 16:00']);

    $this->actingAs(tipsUser(Role::Manager))->post('/staff/team/tips/record', ['week' => '2026-11-02'])
        ->assertSessionHasErrors('week');

    expect(TipAllocation::count())->toBe(0);
});

it('validates the week', function () {
    $this->actingAs(tipsUser(Role::Manager))->post('/staff/team/tips/record', ['week' => 'soon'])->assertSessionHasErrors('week');
});

it('lets only the owner mark a week as paid', function () {
    $manager = tipsUser(Role::Manager);
    $this->actingAs($manager)->post('/staff/team/tips/record', ['week' => '2026-10-26']);

    $this->post('/staff/team/tips/pay', ['week' => '2026-10-26'])->assertForbidden();
    expect(TipAllocation::whereNotNull('paid_at')->count())->toBe(0);

    $this->actingAs(tipsUser(Role::Owner))->post('/staff/team/tips/pay', ['week' => '2026-10-26'])->assertSessionHasNoErrors();

    expect(TipAllocation::whereNull('paid_at')->count())->toBe(0)
        ->and(ActivityLog::where('event', 'tips.paid')->count())->toBe(1);
});

it('marks paid once and locks the week', function () {
    $owner = tipsUser(Role::Owner);
    $this->actingAs($owner)->post('/staff/team/tips/record', ['week' => '2026-10-26']);
    $this->post('/staff/team/tips/pay', ['week' => '2026-10-26']);
    $paidAt = TipAllocation::first()->paid_at;

    $this->travel(1)->hours();
    $this->post('/staff/team/tips/pay', ['week' => '2026-10-26'])->assertSessionHasNoErrors();
    $this->post('/staff/team/tips/record', ['week' => '2026-10-26'])->assertSessionHasErrors('week');

    expect(TipAllocation::first()->paid_at->equalTo($paidAt))->toBeTrue()
        ->and(ActivityLog::where('event', 'tips.paid')->count())->toBe(1)
        ->and(TipAllocation::count())->toBe(2);
});

it('will not mark an unrecorded week as paid', function () {
    $this->actingAs(tipsUser(Role::Owner))->post('/staff/team/tips/pay', ['week' => '2026-10-26'])->assertSessionHasErrors('week');
});

it('shows the recording state and flags tips that changed afterwards', function () {
    $manager = tipsUser(Role::Manager);
    $this->actingAs($manager);

    $this->get('/staff/team?tab=tips&week=2026-10-26')->assertInertia(fn ($page) => $page
        ->where('tips.recording.status', 'none')
        ->where('tips.recording.can_record', true)
        ->where('tips.recording.can_pay', false)
        ->where('tips.recording.differs', false));

    $this->post('/staff/team/tips/record', ['week' => '2026-10-26']);
    Order::factory()->completed()->withTip(300)->create(['completed_at' => '2026-10-29 20:00']);

    $this->get('/staff/team?tab=tips&week=2026-10-26')->assertInertia(fn ($page) => $page
        ->where('tips.recording.status', 'recorded')
        ->where('tips.recording.recorded_by', $manager->name)
        ->where('tips.recording.total', 900)
        ->where('tips.recording.differs', true)
        ->where('tips.total', 1200)
        ->has('tips.recording.rows', 2));
});

it('reports the current week as not ready to record', function () {
    $this->actingAs(tipsUser(Role::Manager))->get('/staff/team?tab=tips&week=2026-11-02')->assertInertia(fn ($page) => $page
        ->where('tips.recording.week_over', false)
        ->where('tips.recording.can_record', false));
});

it('offers payment to the owner only once recorded, and locks after', function () {
    $this->actingAs(tipsUser(Role::Manager))->post('/staff/team/tips/record', ['week' => '2026-10-26']);

    $this->actingAs(tipsUser(Role::Owner))->get('/staff/team?tab=tips&week=2026-10-26')
        ->assertInertia(fn ($page) => $page->where('tips.recording.can_pay', true));

    $this->post('/staff/team/tips/pay', ['week' => '2026-10-26']);

    $this->get('/staff/team?tab=tips&week=2026-10-26')->assertInertia(fn ($page) => $page
        ->where('tips.recording.status', 'paid')
        ->where('tips.recording.can_pay', false)
        ->where('tips.recording.can_record', false));
});

it('exports the recorded rows once the week is recorded', function () {
    $this->anna->forceFill(['name' => 'Anna'])->save();
    $this->actingAs(tipsUser(Role::Owner))->post('/staff/team/tips/record', ['week' => '2026-10-26']);
    Order::factory()->completed()->withTip(900)->create(['completed_at' => '2026-10-29 20:00']);

    $csv = $this->get('/staff/team/tips/export?week=2026-10-26')->streamedContent();

    expect($csv)->toContain('Anna,Waiter,6.00,6.00');
});

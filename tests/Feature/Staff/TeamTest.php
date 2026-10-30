<?php

use App\Actions\Team\TeamRules;
use App\Actions\Team\TipSplit;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Validation\ValidationException;

// Wednesday 28 October 2026, 18:00. The week runs Monday 26th to Sunday 1 November.
beforeEach(fn () => $this->travelTo('2026-10-28 18:00:00'));

function teamUser(Role $role): User
{
    return User::factory()->role($role)->create();
}

function shiftFor(User $user, string $start, string $end, array $state = []): Shift
{
    return Shift::factory()->between($start, $end)->create(['user_id' => $user->id, 'role' => $user->role, ...$state]);
}

function clock(User $user, string $in, ?string $out, ?Shift $shift = null): Attendance
{
    return Attendance::create(['user_id' => $user->id, 'shift_id' => $shift?->id, 'clocked_in_at' => $in, 'clocked_out_at' => $out]);
}

it('lets managers and owners in and nobody else', function (Role $role, int $status) {
    $this->actingAs(teamUser($role))->get('/staff/team')->assertStatus($status);
})->with([
    [Role::Manager, 200], [Role::Owner, 200],
    [Role::Waiter, 403], [Role::Cashier, 403], [Role::Kitchen, 403], [Role::Rider, 403], [Role::Customer, 403],
]);

it('keeps every write behind the team area', function () {
    $waiter = teamUser(Role::Waiter);
    $shift = shiftFor($waiter, '2026-10-29 17:00', '2026-10-29 22:00');

    $this->actingAs($waiter);
    $this->post('/staff/team/people', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'waiter'])->assertForbidden();
    $this->post('/staff/team/shifts', [])->assertForbidden();
    $this->delete("/staff/team/shifts/{$shift->id}")->assertForbidden();
    $this->get('/staff/team/attendance/export')->assertForbidden();
    $this->get('/staff/team/tips/export')->assertForbidden();
});

it('lists the staff with shift status and hours this week', function () {
    $manager = teamUser(Role::Manager);
    $cook = teamUser(Role::Kitchen);
    clock($cook, '2026-10-28 12:00', null);
    clock($cook, '2026-10-27 12:00', '2026-10-27 16:30');
    teamUser(Role::Customer);

    $this->actingAs($manager)->get('/staff/team')->assertInertia(fn ($page) => $page
        ->component('staff/team/index')
        ->has('people', 2)
        ->where('people.0.id', fn ($id) => in_array($id, [$manager->id, $cook->id], true))
        ->where('people', fn ($people) => collect($people)->firstWhere('id', $cook->id)['on_shift'] === true
            && collect($people)->firstWhere('id', $cook->id)['minutes'] === 4 * 60 + 30 + 6 * 60)
        ->where('assignable_roles', ['waiter', 'cashier', 'kitchen', 'rider']));
});

it('adds a staff member with an unusable random password', function () {
    $owner = teamUser(Role::Owner);

    $this->actingAs($owner)->post('/staff/team/people', [
        'name' => 'Asha Patel', 'email' => 'asha@example.com', 'phone' => '07700 900123', 'role' => 'manager',
    ])->assertRedirect();

    $asha = User::where('email', 'asha@example.com')->firstOrFail();
    expect($asha->role)->toBe(Role::Manager)
        ->and($asha->password)->not->toBeEmpty()
        ->and($asha->email_verified_at)->not->toBeNull();
    expect(ActivityLog::where('event', 'team.added')->count())->toBe(1);
});

it('stops managers creating or promoting owners and managers', function (string $role) {
    $manager = teamUser(Role::Manager);
    $waiter = teamUser(Role::Waiter);

    $this->actingAs($manager)->post('/staff/team/people', ['name' => 'N', 'email' => 'n@example.com', 'role' => $role])->assertForbidden();
    $this->actingAs($manager)->patch("/staff/team/people/{$waiter->id}", ['role' => $role])->assertForbidden();

    expect($waiter->refresh()->role)->toBe(Role::Waiter);
})->with(['manager', 'owner']);

it('lets managers add and move front-line staff', function () {
    $manager = teamUser(Role::Manager);
    $waiter = teamUser(Role::Waiter);

    $this->actingAs($manager)->post('/staff/team/people', ['name' => 'Rae', 'email' => 'rae@example.com', 'role' => 'rider'])->assertRedirect();
    $this->actingAs($manager)->patch("/staff/team/people/{$waiter->id}", ['role' => 'cashier', 'phone' => '07700 900999'])->assertRedirect();

    expect($waiter->refresh()->role)->toBe(Role::Cashier)->and($waiter->phone)->toBe('07700 900999');
    expect(User::where('email', 'rae@example.com')->value('role'))->toBe(Role::Rider);
});

it('stops managers editing managers and owners', function () {
    $manager = teamUser(Role::Manager);

    foreach ([teamUser(Role::Manager), teamUser(Role::Owner)] as $other) {
        $this->actingAs($manager)->patch("/staff/team/people/{$other->id}", ['phone' => '07700 900000'])->assertForbidden();
    }
});

it('lets owners promote people', function () {
    $owner = teamUser(Role::Owner);
    $waiter = teamUser(Role::Waiter);

    $this->actingAs($owner)->patch("/staff/team/people/{$waiter->id}", ['role' => 'manager'])->assertRedirect();

    expect($waiter->refresh()->role)->toBe(Role::Manager);
    expect(ActivityLog::where('event', 'team.role_changed')->count())->toBe(1);
});

it('never lets anyone change their own role', function (Role $role) {
    $me = teamUser($role);
    teamUser(Role::Owner);

    $this->actingAs($me)->patch("/staff/team/people/{$me->id}", ['role' => 'waiter'])->assertSessionHasErrors('role');
    $this->actingAs($me)->delete("/staff/team/people/{$me->id}")->assertForbidden();

    expect($me->refresh()->role)->toBe($role);
})->with([Role::Manager, Role::Owner]);

it('lets people edit their own phone', function () {
    $manager = teamUser(Role::Manager);

    $this->actingAs($manager)->patch("/staff/team/people/{$manager->id}", ['phone' => '07700 900321'])->assertRedirect();

    expect($manager->refresh()->phone)->toBe('07700 900321')->and($manager->role)->toBe(Role::Manager);
});

it('always keeps one owner', function () {
    $only = teamUser(Role::Owner);

    expect(fn () => TeamRules::ensureOwnerRemains($only, Role::Manager))->toThrow(ValidationException::class);

    $second = teamUser(Role::Owner);
    TeamRules::ensureOwnerRemains($only, Role::Manager);

    $this->actingAs($second)->patch("/staff/team/people/{$only->id}", ['role' => 'manager'])->assertRedirect();
    expect($only->refresh()->role)->toBe(Role::Manager);
});

it('removes someone from the team for owners only', function () {
    $owner = teamUser(Role::Owner);
    $manager = teamUser(Role::Manager);
    $waiter = teamUser(Role::Waiter);
    $future = shiftFor($waiter, '2026-10-30 17:00', '2026-10-30 22:00');
    $past = shiftFor($waiter, '2026-10-26 17:00', '2026-10-26 22:00');

    $this->actingAs($manager)->delete("/staff/team/people/{$waiter->id}")->assertForbidden();

    $this->actingAs($owner)->delete("/staff/team/people/{$waiter->id}")->assertRedirect();

    expect($waiter->refresh()->role)->toBe(Role::Customer);
    expect(Shift::find($future->id))->toBeNull()->and(Shift::find($past->id))->not->toBeNull();
});

it('creates a shift and flags ones outside opening hours without blocking', function () {
    $manager = teamUser(Role::Manager);
    $waiter = teamUser(Role::Waiter);

    $this->actingAs($manager)->post('/staff/team/shifts', [
        'user_id' => $waiter->id, 'date' => '2026-10-29', 'start' => '17:00', 'end' => '22:30', 'role' => 'waiter',
    ])->assertRedirect()->assertInertiaFlash('toast.type', 'success');

    $this->actingAs($manager)->post('/staff/team/shifts', [
        'user_id' => $waiter->id, 'date' => '2026-10-26', 'start' => '17:00', 'end' => '22:00', 'role' => 'waiter',
    ])->assertRedirect()->assertInertiaFlash('toast.type', 'warning');

    expect(Shift::count())->toBe(2);
});

it('refuses overlapping shifts for the same person but allows back to back', function () {
    $manager = teamUser(Role::Manager);
    $waiter = teamUser(Role::Waiter);
    $existing = shiftFor($waiter, '2026-10-29 12:00', '2026-10-29 16:00');
    $body = ['user_id' => $waiter->id, 'date' => '2026-10-29', 'role' => 'waiter'];

    $this->actingAs($manager)->post('/staff/team/shifts', [...$body, 'start' => '15:00', 'end' => '20:00'])->assertSessionHasErrors('start');
    $this->actingAs($manager)->post('/staff/team/shifts', [...$body, 'start' => '16:00', 'end' => '20:00'])->assertSessionHasNoErrors();
    $this->actingAs($manager)->patch("/staff/team/shifts/{$existing->id}", [...$body, 'start' => '12:00', 'end' => '17:00'])->assertSessionHasErrors('start');
    $this->actingAs($manager)->patch("/staff/team/shifts/{$existing->id}", [...$body, 'start' => '11:00', 'end' => '16:00'])->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(2);
});

it('treats an end before the start as past midnight and refuses people not on the team', function () {
    $manager = teamUser(Role::Manager);
    $waiter = teamUser(Role::Waiter);

    $this->actingAs($manager)->post('/staff/team/shifts', ['user_id' => $waiter->id, 'date' => '2026-10-30', 'start' => '18:00', 'end' => '01:00', 'role' => 'waiter'])->assertSessionHasNoErrors();
    expect(Shift::first()->ends_at->toDateTimeString())->toBe('2026-10-31 01:00:00');

    $this->actingAs($manager)->post('/staff/team/shifts', ['user_id' => teamUser(Role::Customer)->id, 'date' => '2026-10-30', 'start' => '18:00', 'end' => '22:00', 'role' => 'waiter'])->assertSessionHasErrors('user_id');
});

it('deletes a shift and logs it', function () {
    $waiter = teamUser(Role::Waiter);
    $shift = shiftFor($waiter, '2026-10-29 17:00', '2026-10-29 22:00');

    $this->actingAs(teamUser(Role::Manager))->delete("/staff/team/shifts/{$shift->id}")->assertRedirect();

    expect(Shift::count())->toBe(0)->and(ActivityLog::where('event', 'shift.deleted')->count())->toBe(1);
});

it('copies last week and reports the clashes it skipped', function () {
    $manager = teamUser(Role::Manager);
    $asha = teamUser(Role::Waiter);
    $ben = teamUser(Role::Kitchen);
    shiftFor($asha, '2026-10-20 17:00', '2026-10-20 22:00');
    shiftFor($ben, '2026-10-21 17:00', '2026-10-21 22:00');
    shiftFor($ben, '2026-10-22 17:00', '2026-10-22 22:00');
    shiftFor($asha, '2026-10-27 18:00', '2026-10-27 23:00');

    $this->actingAs($manager)->post('/staff/team/shifts/copy-week', ['week' => '2026-10-26'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'warning');

    expect(Shift::where('starts_at', '>=', '2026-10-26')->count())->toBe(3);
    expect(Shift::where('user_id', $ben->id)->where('starts_at', '2026-10-28 17:00:00')->exists())->toBeTrue();
    expect(Shift::where('user_id', $asha->id)->where('starts_at', '2026-10-27 17:00:00')->exists())->toBeFalse();
    expect(ActivityLog::where('event', 'shift.copied')->first()->properties['skipped'])->toHaveCount(1);
});

it('shows the rota with hours per person, covers per day and the shift blocks', function () {
    $manager = teamUser(Role::Manager);
    $waiter = teamUser(Role::Waiter);
    shiftFor($waiter, '2026-10-29 17:00', '2026-10-29 22:00');
    shiftFor($waiter, '2026-10-30 12:00', '2026-10-30 16:00');
    Reservation::factory()->create(['starts_at' => '2026-10-29 19:00', 'ends_at' => '2026-10-29 20:45', 'party_size' => 4, 'status' => ReservationStatus::Confirmed]);

    $this->actingAs($manager)->get('/staff/team?tab=rota&week=2026-10-28')->assertInertia(fn ($page) => $page
        ->where('week.start', '2026-10-26')
        ->where('rota.days.3.covers', 4)
        ->where('rota.people', fn ($people) => collect($people)->firstWhere('id', $waiter->id)['minutes'] === 9 * 60)
        ->where('people', null));
});

it('works out late arrivals, forgotten clock-outs and hours with frozen time', function () {
    $manager = teamUser(Role::Manager);
    $asha = teamUser(Role::Waiter);
    $ben = teamUser(Role::Kitchen);
    $cat = teamUser(Role::Cashier);

    $s1 = shiftFor($asha, '2026-10-27 17:00', '2026-10-27 22:00');
    $s2 = shiftFor($asha, '2026-10-28 12:00', '2026-10-28 16:00');
    $b1 = shiftFor($ben, '2026-10-27 17:00', '2026-10-27 22:00');
    shiftFor($cat, '2026-10-27 12:00', '2026-10-27 16:00');

    clock($asha, '2026-10-27 17:05', '2026-10-27 22:00', $s1);
    clock($asha, '2026-10-28 12:06', '2026-10-28 16:00', $s2);
    clock($ben, '2026-10-27 17:20', '2026-10-27 21:50', $b1);
    clock($ben, '2026-10-27 06:00', null);

    $this->actingAs($manager)->get('/staff/team?tab=attendance&week=2026-10-26')->assertInertia(function ($page) use ($asha, $ben, $cat) {
        $page->where('attendance', function ($rows) use ($asha, $ben, $cat) {
            $rows = collect($rows)->keyBy('id');

            expect($rows[$asha->id]['late'])->toBe(1)
                ->and($rows[$asha->id]['rostered_minutes'])->toBe(540)
                ->and($rows[$asha->id]['clocked_minutes'])->toBe(295 + 234)
                ->and($rows[$asha->id]['missed'])->toBe(0)
                ->and($rows[$ben->id]['late'])->toBe(1)
                ->and($rows[$ben->id]['missed'])->toBe(1)
                ->and($rows[$ben->id]['clocked_minutes'])->toBe(270)
                ->and($rows[$cat->id]['no_shows'])->toBe(1);

            return true;
        });
    });
});

it('fixes a missed clock-out with a reason and logs it', function () {
    $manager = teamUser(Role::Manager);
    $ben = teamUser(Role::Kitchen);
    $open = clock($ben, '2026-10-27 12:00', null);

    $this->actingAs($manager)->patch("/staff/team/attendance/{$open->id}", ['clocked_out_at' => '2026-10-27 23:00', 'reason' => 'x'])->assertSessionHasErrors('reason');
    $this->actingAs($manager)->patch("/staff/team/attendance/{$open->id}", ['clocked_out_at' => '2026-10-27T11:00', 'reason' => 'Left at close'])->assertSessionHasErrors('clocked_out_at');
    $this->actingAs($manager)->patch("/staff/team/attendance/{$open->id}", ['clocked_out_at' => '2026-10-27T23:00', 'reason' => 'Left at close'])->assertSessionHasNoErrors();

    expect($open->refresh()->clocked_out_at->toDateTimeString())->toBe('2026-10-27 23:00:00');
    expect(ActivityLog::where('event', 'attendance.fixed')->first()->properties['reason'])->toBe('Left at close');
});

it('will not fix a clock-in that is still recent', function () {
    $recent = clock(teamUser(Role::Waiter), '2026-10-28 12:00', null);

    $this->actingAs(teamUser(Role::Manager))->patch("/staff/team/attendance/{$recent->id}", ['clocked_out_at' => '2026-10-28T17:00', 'reason' => 'Went home'])->assertSessionHasErrors('clocked_out_at');
});

it('exports the week for payroll', function () {
    $ben = teamUser(Role::Kitchen);
    $ben->forceFill(['name' => '=Ben Cook', 'email' => 'ben@example.com'])->save();
    $s = shiftFor($ben, '2026-10-27 17:00', '2026-10-27 22:00');
    clock($ben, '2026-10-27 17:10', '2026-10-27 21:40', $s);
    clock($ben, '2026-10-26 06:00', null);
    clock(teamUser(Role::Waiter), '2026-10-20 12:00', '2026-10-20 16:00');

    $response = $this->actingAs(teamUser(Role::Manager))->get('/staff/team/attendance/export?week=2026-10-26');
    $response->assertOk()->assertDownload('attendance-2026-10-26.csv');

    $lines = array_values(array_filter(explode("\n", $response->streamedContent())));

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toStartWith('Name,Email,Role,Date,')
        ->and($lines[1])->toContain("\"'=Ben Cook\",ben@example.com,Kitchen,2026-10-26,06:00,,0.00,,0,\"Missed clock-out\"")
        ->and($lines[2])->toContain('2026-10-27,17:10,21:40,4.50,17:00–22:00,10');
});

it('splits tips by hours so the shares add up to the pot', function (int $total, array $minutes) {
    $shares = TipSplit::allocate($total, $minutes);

    expect(array_sum($shares))->toBe($total)->and(array_keys($shares))->toEqualCanonicalizing(array_keys($minutes));
    expect(TipSplit::allocate($total, $minutes))->toBe($shares);
})->with([
    [1000, [1 => 600, 2 => 300, 3 => 100]],
    [1001, [1 => 333, 2 => 333, 3 => 333]],
    [1, [5 => 60, 2 => 60, 9 => 60]],
    [9999, [1 => 457, 2 => 211, 3 => 989, 4 => 13]],
    [0, [1 => 60]],
]);

it('gives leftover pence to the largest remainders, lowest id first on a tie', function () {
    expect(TipSplit::allocate(100, [3 => 60, 1 => 60, 2 => 60]))->toBe([1 => 34, 2 => 33, 3 => 33]);
    expect(TipSplit::allocate(100, [1 => 120, 2 => 60, 3 => 0]))->toBe([1 => 67, 2 => 33]);
});

it('shows the weekly tips pot and the split between people who worked', function () {
    $asha = teamUser(Role::Waiter);
    $ben = teamUser(Role::Kitchen);
    teamUser(Role::Rider);
    clock($asha, '2026-10-27 12:00', '2026-10-27 16:00');
    clock($ben, '2026-10-27 12:00', '2026-10-27 14:00');

    Order::factory()->completed()->withTip(500)->create(['completed_at' => '2026-10-27 20:00']);
    Order::factory()->completed()->withTip(250)->create(['completed_at' => '2026-10-28 13:00']);
    Order::factory()->completed()->withTip(900)->create(['completed_at' => '2026-10-20 13:00']);
    Order::factory()->cancelled()->withTip(700)->create();

    $this->actingAs(teamUser(Role::Manager))->get('/staff/team?tab=tips&week=2026-10-26')->assertInertia(fn ($page) => $page
        ->where('tips.total', 750)
        ->where('tips.orders', 2)
        ->has('tips.shares', 2)
        ->where('tips.shares', fn ($shares) => collect($shares)->sum('share') === 750
            && collect($shares)->firstWhere('id', $asha->id)['share'] === 500
            && collect($shares)->firstWhere('id', $ben->id)['share'] === 250));
});

it('exports the tips split', function () {
    $asha = teamUser(Role::Waiter);
    $asha->forceFill(['name' => 'Asha'])->save();
    clock($asha, '2026-10-27 12:00', '2026-10-27 16:00');
    Order::factory()->completed()->withTip(333)->create(['completed_at' => '2026-10-27 20:00']);

    $response = $this->actingAs(teamUser(Role::Owner))->get('/staff/team/tips/export?week=2026-10-26');

    expect($response->streamedContent())->toContain('Asha,Waiter,4.00,3.33');
});

it('leaves the phone alone when an update does not mention it', function () {
    $manager = teamUser(Role::Manager);
    $waiter = teamUser(Role::Waiter);
    $waiter->forceFill(['phone' => '07700 900123'])->save();

    $this->actingAs($manager)->patch("/staff/team/people/{$waiter->id}", ['role' => 'cashier'])->assertSessionHasNoErrors();
    expect($waiter->refresh()->phone)->toBe('07700 900123')->and($waiter->role)->toBe(Role::Cashier);

    $this->actingAs($manager)->patch("/staff/team/people/{$waiter->id}", ['phone' => null])->assertSessionHasNoErrors();
    expect($waiter->refresh()->phone)->toBeNull();
});

it('lets managers roster front-line staff and themselves but not managers or owners', function (Role $target) {
    $manager = teamUser(Role::Manager);
    $person = teamUser($target);
    $body = ['user_id' => $person->id, 'date' => '2026-10-29', 'start' => '17:00', 'end' => '22:00', 'role' => $target->value];

    $this->actingAs($manager)->post('/staff/team/shifts', $body)->assertForbidden();

    $existing = shiftFor($person, '2026-10-30 17:00', '2026-10-30 22:00');
    $this->actingAs($manager)->patch("/staff/team/shifts/{$existing->id}", [...$body, 'date' => '2026-10-30'])->assertForbidden();
    $this->actingAs($manager)->delete("/staff/team/shifts/{$existing->id}")->assertForbidden();

    expect(Shift::count())->toBe(1);
})->with([Role::Owner, Role::Manager]);

it('lets managers roster themselves and owners roster anyone', function () {
    $manager = teamUser(Role::Manager);
    $owner = teamUser(Role::Owner);

    $this->actingAs($manager)->post('/staff/team/shifts', [
        'user_id' => $manager->id, 'date' => '2026-10-29', 'start' => '17:00', 'end' => '22:00', 'role' => 'manager',
    ])->assertSessionHasNoErrors();

    $this->actingAs($owner)->post('/staff/team/shifts', [
        'user_id' => $manager->id, 'date' => '2026-10-30', 'start' => '17:00', 'end' => '22:00', 'role' => 'manager',
    ])->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(2);
});

it('skips shifts a manager may not copy and lets owners copy everything', function () {
    $manager = teamUser(Role::Manager);
    $owner = teamUser(Role::Owner);
    $waiter = teamUser(Role::Waiter);
    shiftFor($waiter, '2026-10-20 17:00', '2026-10-20 22:00');
    shiftFor($owner, '2026-10-21 17:00', '2026-10-21 22:00');

    $this->actingAs($manager)->post('/staff/team/shifts/copy-week', ['week' => '2026-10-26'])->assertRedirect();
    expect(Shift::where('starts_at', '>=', '2026-10-26')->pluck('user_id')->all())->toBe([$waiter->id]);

    $this->actingAs($owner)->post('/staff/team/shifts/copy-week', ['week' => '2026-10-26'])->assertRedirect();
    expect(Shift::where('user_id', $owner->id)->where('starts_at', '>=', '2026-10-26')->count())->toBe(1);
});

it('flags on the rota which people the viewer may roster', function () {
    $manager = teamUser(Role::Manager);
    $owner = teamUser(Role::Owner);

    $this->actingAs($manager)->get('/staff/team?tab=rota')->assertInertia(fn ($page) => $page
        ->where('rota.people', fn ($people) => collect($people)->firstWhere('id', $owner->id)['can_roster'] === false
            && collect($people)->firstWhere('id', $manager->id)['can_roster'] === true));
});

it('stops a manager fixing their own, another manager\'s or the owner\'s clock-out', function (callable $pick) {
    $manager = teamUser(Role::Manager);
    $subject = $pick($manager);
    $open = clock($subject, '2026-10-27 12:00', null);

    $this->actingAs($manager)->patch("/staff/team/attendance/{$open->id}", ['clocked_out_at' => '2026-10-27T23:00', 'reason' => 'Left at close'])->assertForbidden();

    expect($open->refresh()->clocked_out_at)->toBeNull();
})->with([
    'themselves' => [fn (User $m) => $m],
    'another manager' => [fn () => teamUser(Role::Manager)],
    'the owner' => [fn () => teamUser(Role::Owner)],
]);

it('lets an owner fix a manager\'s clock-out', function () {
    $manager = teamUser(Role::Manager);
    $open = clock($manager, '2026-10-27 12:00', null);

    $this->actingAs(teamUser(Role::Owner))->patch("/staff/team/attendance/{$open->id}", ['clocked_out_at' => '2026-10-27T23:00', 'reason' => 'Left at close'])->assertSessionHasNoErrors();

    expect($open->refresh()->clocked_out_at)->not->toBeNull();
});

it('rejects fixing a clock-out that has already been set', function () {
    $done = clock(teamUser(Role::Waiter), '2026-10-27 12:00', '2026-10-27 20:00');

    $this->actingAs(teamUser(Role::Manager))->patch("/staff/team/attendance/{$done->id}", ['clocked_out_at' => '2026-10-27T23:00', 'reason' => 'Left at close'])->assertSessionHasErrors('clocked_out_at');

    expect($done->refresh()->clocked_out_at->toDateTimeString())->toBe('2026-10-27 20:00:00');
});

it('still blocks demoting or removing the last owner', function () {
    $owner = teamUser(Role::Owner);

    expect(fn () => TeamRules::ensureOwnerRemains($owner, Role::Manager))->toThrow(ValidationException::class);
    expect(fn () => TeamRules::ensureOwnerRemains($owner, Role::Customer))->toThrow(ValidationException::class);

    teamUser(Role::Owner);
    TeamRules::ensureOwnerRemains($owner, Role::Manager);
    expect(true)->toBeTrue();
});

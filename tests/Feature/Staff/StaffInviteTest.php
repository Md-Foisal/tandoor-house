<?php

use App\Actions\Team\InviteStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\Staff\StaffInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function inviteStaff(User $actor, array $extra = []): void
{
    test()->actingAs($actor)->post('/staff/team/people', [
        'name' => 'Priya Nair', 'email' => 'priya@example.test', 'role' => 'waiter', ...$extra,
    ])->assertRedirect();
}

function inviteToken(): string
{
    $token = '';
    Notification::assertSentTo(User::where('email', 'priya@example.test')->first(), StaffInvite::class, function (StaffInvite $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    return $token;
}

it('emails an invite when someone is added and logs it', function () {
    Notification::fake();
    $manager = User::factory()->role(Role::Manager)->create(['name' => 'Asha Manager']);

    inviteStaff($manager);

    $user = User::where('email', 'priya@example.test')->first();
    Notification::assertSentTo($user, StaffInvite::class);
    expect(ActivityLog::where('event', 'automation.team.invite_sent')->count())->toBe(1);

    $mail = (new StaffInvite('tok', 'Asha Manager'))->toMail($user)->render();
    expect((string) $mail)->toContain('Asha Manager')->toContain('Waiter')->toContain(config('restaurant.name'))->toContain('invite/tok');
});

it('lets the invitee set a password once, within seven days', function () {
    Notification::fake();
    inviteStaff(User::factory()->role(Role::Owner)->create());
    $token = inviteToken();
    $user = User::where('email', 'priya@example.test')->first();
    auth()->logout();

    $this->get("/invite/{$token}?email=priya@example.test")->assertInertia(fn ($page) => $page->component('auth/accept-invite')->where('valid', true));

    $this->post("/invite/{$token}", ['email' => $user->email, 'password' => 'Sup3r-secret-pass', 'password_confirmation' => 'Sup3r-secret-pass'])
        ->assertRedirect(route('login'));

    expect(Hash::check('Sup3r-secret-pass', $user->refresh()->password))->toBeTrue();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
    expect((new InviteStatus)->forUser($user)['state'])->toBe('accepted');

    $this->get("/invite/{$token}?email=priya@example.test")->assertInertia(fn ($page) => $page->where('valid', false));
});

it('refuses an expired or wrong link', function () {
    Notification::fake();
    inviteStaff(User::factory()->role(Role::Owner)->create());
    $token = inviteToken();
    $user = User::where('email', 'priya@example.test')->first();
    auth()->logout();
    $body = ['email' => $user->email, 'password' => 'Sup3r-secret-pass', 'password_confirmation' => 'Sup3r-secret-pass'];

    $this->post('/invite/wrong-token', $body)->assertSessionHasErrors('email');

    $this->travel(7)->days();
    $this->travel(1)->minutes();
    expect((new InviteStatus)->forUser($user)['state'])->toBe('expired');
    $this->post("/invite/{$token}", $body)->assertSessionHasErrors('email');
});

it('resends a fresh invite, throttled, only while no password is set', function () {
    Notification::fake();
    $owner = User::factory()->role(Role::Owner)->create();
    inviteStaff($owner);
    $user = User::where('email', 'priya@example.test')->first();
    $old = inviteToken();

    $this->actingAs($owner)->post("/staff/team/people/{$user->id}/invite")->assertRedirect();
    Notification::assertSentToTimes($user, StaffInvite::class, 2);
    expect(Hash::check($old, DB::table('password_reset_tokens')->value('token')))->toBeFalse();

    $this->post("/staff/team/people/{$user->id}/invite")->assertRedirect();
    $this->post("/staff/team/people/{$user->id}/invite")->assertRedirect();
    $this->post("/staff/team/people/{$user->id}/invite")->assertSessionHasErrors('invite');
    Notification::assertSentToTimes($user, StaffInvite::class, 4);

    $done = User::factory()->role(Role::Waiter)->create();
    $this->post("/staff/team/people/{$done->id}/invite")->assertSessionHasErrors('invite');
});

it('applies the team rules to who may be re-invited', function () {
    Notification::fake();
    $owner = User::factory()->role(Role::Owner)->create();
    inviteStaff($owner, ['role' => 'manager', 'email' => 'priya@example.test']);
    $manager = User::where('email', 'priya@example.test')->first();

    $this->actingAs(User::factory()->role(Role::Manager)->create())->post("/staff/team/people/{$manager->id}/invite")->assertForbidden();
    $this->actingAs(User::factory()->role(Role::Waiter)->create())->post("/staff/team/people/{$manager->id}/invite")->assertForbidden();
    $this->actingAs($owner)->post("/staff/team/people/{$owner->id}/invite")->assertForbidden();
});

it('shows the invite state in the people list', function () {
    Notification::fake();
    $owner = User::factory()->role(Role::Owner)->create();
    inviteStaff($owner);

    $people = collect($this->actingAs($owner)->get('/staff/team')->viewData('page')['props']['people']);

    expect($people->firstWhere('email', 'priya@example.test')['invite']['state'])->toBe('pending')
        ->and($people->firstWhere('email', 'priya@example.test')['can_invite'])->toBeTrue()
        ->and($people->firstWhere('id', $owner->id)['can_invite'])->toBeFalse();
});

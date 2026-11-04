<?php

use App\Enums\Role;
use App\Models\User;
use App\Payments\Gateways\FakeGateway;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

it('hides the demo from a normal install', function () {
    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('demo', null));
});

it('lists the demo accounts on the sign-in page', function () {
    config(['demo.enabled' => true]);

    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('demo.accounts.owner', 'owner@tandoorhouse.test')
        ->where('demo.password', 'password'));
});

it('refuses the fake gateway in production unless the demo is on', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => new FakeGateway)->toThrow(Exception::class);

    config(['demo.enabled' => true]);

    expect(new FakeGateway)->toBeInstanceOf(FakeGateway::class);
});

it('keeps shared credentials from changing in the demo', function () {
    config(['demo.enabled' => true]);
    $user = User::factory()->create(['role' => Role::Customer, 'password' => Hash::make('password')]);

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'a-brand-new-password-1',
            'password_confirmation' => 'a-brand-new-password-1',
        ])->assertRedirect();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('keeps demo accounts from being deleted', function () {
    config(['demo.enabled' => true]);
    $user = User::factory()->create(['role' => Role::Customer]);

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertRedirect();

    expect($user->fresh())->not->toBeNull();
});

it('leaves staff records alone in the demo', function () {
    config(['demo.enabled' => true]);
    $owner = User::factory()->create(['role' => Role::Owner]);
    $waiter = User::factory()->create(['role' => Role::Waiter]);

    $this->actingAs($owner)->delete("/staff/team/people/{$waiter->id}")->assertRedirect();

    expect($waiter->fresh())->not->toBeNull();
});

it('still lets a guest browse and write outside the protected paths', function () {
    config(['demo.enabled' => true]);

    $this->get('/menu')->assertOk();
    $this->get('/book')->assertOk();
});

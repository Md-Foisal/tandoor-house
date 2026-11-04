<?php

use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('sends guests the public strings only', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('translations.home')
        ->has('translations.ui')
        ->missing('translations.staff')
        ->missing('translations.staff_orders')
        ->missing('translations.staff_team'));
});

it('sends customers the public strings only', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->has('translations.menu')->missing('translations.staff_orders'));
});

it('sends staff every group', function () {
    $this->actingAs(User::factory()->role(Role::Manager)->create())
        ->get('/staff/orders')
        ->assertInertia(fn (Assert $page) => $page
            ->has('translations.staff')
            ->has('translations.staff_orders')
            ->has('translations.ui'));
});

it('gives a guest opening a staff invite the team strings it needs', function () {
    $this->get('/invite/not-a-real-token')
        ->assertInertia(fn (Assert $page) => $page
            ->has('translations.staff_team.invite.page')
            ->missing('translations.staff_orders'));
});

it('keys the cached strings by audience so signing in swaps them', function () {
    $guest = $this->get('/')->viewData('page');
    $staff = $this->actingAs(User::factory()->role(Role::Manager)->create())->get('/staff/orders')->viewData('page');

    expect($guest['onceProps'])->toHaveKey('translations-public')
        ->and($staff['onceProps'])->toHaveKey('translations-staff');
});

<?php

use App\Enums\Role;
use App\Enums\StaffArea;
use App\Models\User;

it('keeps customers and guests out of the staff panel', function () {
    $this->get('/staff')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/staff')->assertForbidden();
});

it('lets every staff role in, sharing only the areas the role may use', function (Role $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get('/staff')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'staffAreas',
            array_map(fn (StaffArea $area) => $area->value, StaffArea::for($role)),
        ));
})->with([Role::Waiter, Role::Cashier, Role::Kitchen, Role::Rider, Role::Manager, Role::Owner]);

it('sends staff to the panel and customers to their account after signing in', function () {
    $waiter = User::factory()->role(Role::Waiter)->create();
    $customer = User::factory()->create();

    $this->post('/login', ['email' => $waiter->email, 'password' => 'password'])->assertRedirect('/staff');
    auth()->logout();
    $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect('/account');
});

it('gives only managers and owners the back office', function () {
    expect(StaffArea::Reports->allows(Role::Cashier))->toBeFalse()
        ->and(StaffArea::Reports->allows(Role::Manager))->toBeTrue()
        ->and(StaffArea::Kitchen->allows(Role::Waiter))->toBeFalse()
        ->and(StaffArea::Deliveries->allows(Role::Rider))->toBeTrue();
});

<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Tests\Feature\Policies\RoleMatrix;

/** A delivery run handed to the acting rider, or to nobody for anyone else. */
function policyRun(?User $actor, OrderStatus $status): Order
{
    return Order::factory()->delivery()->live($status)->create([
        'rider_id' => $actor?->role === Role::Rider ? $actor->id : null,
    ]);
}

dataset('policy delivery writes', [
    'pick up own run' => ['post', fn (?User $actor) => '/staff/deliveries/'.policyRun($actor, OrderStatus::Ready)->number.'/pickup', RoleMatrix::DELIVERIES],
    'deliver own run' => ['post', fn (?User $actor) => '/staff/deliveries/'.policyRun($actor, OrderStatus::OutForDelivery)->number.'/deliver', RoleMatrix::DELIVERIES],
    'fail own run' => ['post', fn (?User $actor) => '/staff/deliveries/'.policyRun($actor, OrderStatus::OutForDelivery)->number.'/fail', RoleMatrix::DELIVERIES],
    'pick up another rider\'s run' => ['post', fn () => '/staff/deliveries/'.Order::factory()->delivery()->live(OrderStatus::Ready)->create([
        'rider_id' => User::factory()->role(Role::Rider)->create()->id,
    ])->number.'/pickup', RoleMatrix::MANAGEMENT],
    'assign a rider' => ['patch', fn () => '/staff/deliveries/'.policyRun(null, OrderStatus::Ready)->number.'/rider', RoleMatrix::MANAGEMENT],
]);

it('guards every delivery write by role', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy delivery writes')->with(RoleMatrix::ROLES);

<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Tests\Feature\Policies\RoleMatrix;

function policyDineInOrder(): Order
{
    return Order::factory()->dineIn()->live()->withLines(1)->create();
}

dataset('policy order writes', [
    'move an order on' => ['post', fn () => '/staff/orders/'.policyDineInOrder()->number.'/status', RoleMatrix::FLOOR],
    'edit order lines' => ['patch', fn () => '/staff/orders/'.policyDineInOrder()->number.'/lines', RoleMatrix::FLOOR],
    'refund an order' => ['post', fn () => '/staff/orders/'.policyDineInOrder()->number.'/refund', ['cashier', 'manager', 'owner']],
    'take payment at a table' => ['post', fn () => '/staff/orders/'.policyDineInOrder()->number.'/payment', RoleMatrix::FLOOR],
    'take payment for a pickup' => ['post', fn () => '/staff/orders/'.Order::factory()->live()->create()->number.'/payment', ['cashier', 'manager', 'owner']],
    'assign a rider' => ['patch', fn () => '/staff/orders/'.Order::factory()->delivery()->live(OrderStatus::Ready)->create()->number.'/rider', RoleMatrix::MANAGEMENT],
    'print a receipt' => ['get', fn () => '/staff/orders/'.policyDineInOrder()->number.'/receipt', RoleMatrix::FLOOR],
    'download a receipt' => ['get', fn () => '/staff/orders/'.policyDineInOrder()->number.'/receipt.pdf', RoleMatrix::FLOOR],
]);

it('guards every order board write by role', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy order writes')->with(RoleMatrix::ROLES);

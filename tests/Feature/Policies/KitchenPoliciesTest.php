<?php

use App\Enums\LineStatus;
use App\Models\MenuItem;
use App\Models\Order;
use Tests\Feature\Policies\RoleMatrix;

function policyCookingOrder(): Order
{
    return Order::factory()->live()->withLines(1)->create();
}

dataset('policy kitchen writes', [
    'bump a line' => ['post', fn () => '/staff/kitchen/lines/'.policyCookingOrder()->lines()->firstOrFail()->id.'/bump', RoleMatrix::KITCHEN],
    'recall a line' => ['post', function () {
        $line = policyCookingOrder()->lines()->firstOrFail();
        $line->forceFill(['status' => LineStatus::Ready])->save();

        return '/staff/kitchen/lines/'.$line->id.'/recall';
    }, RoleMatrix::KITCHEN],
    'bump an order' => ['post', fn () => '/staff/kitchen/orders/'.policyCookingOrder()->id.'/bump', RoleMatrix::KITCHEN],
    'mark a dish sold out' => ['post', fn () => '/staff/kitchen/items/'.MenuItem::factory()->create()->id.'/sold-out', RoleMatrix::KITCHEN],
]);

it('guards every kitchen write by role', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy kitchen writes')->with(RoleMatrix::ROLES);

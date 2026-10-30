<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Tests\Feature\Policies\RoleMatrix;

$signedIn = ['customer', ...RoleMatrix::STAFF];

dataset('policy account writes', [
    'add an address' => ['post', fn () => '/account/addresses', $signedIn],
    'edit own address' => ['patch', fn (?User $actor) => '/account/addresses/'.Address::factory()->create(['user_id' => $actor?->id ?? User::factory()])->id, $signedIn],
    'edit someone else\'s address' => ['patch', fn () => '/account/addresses/'.Address::factory()->create()->id, []],
    'delete own address' => ['delete', fn (?User $actor) => '/account/addresses/'.Address::factory()->create(['user_id' => $actor?->id ?? User::factory()])->id, $signedIn],
    'delete someone else\'s address' => ['delete', fn () => '/account/addresses/'.Address::factory()->create()->id, []],
    'reorder own order' => ['post', fn (?User $actor) => '/account/orders/'.Order::factory()->completed()->create(['user_id' => $actor?->id])->number.'/reorder', $signedIn],
    'reorder someone else\'s order' => ['post', fn () => '/account/orders/'.Order::factory()->completed()->forCustomer(User::factory()->create())->create()->number.'/reorder', []],
    'review own completed order' => ['post', fn (?User $actor) => '/account/orders/'.Order::factory()->completed()->create(['user_id' => $actor?->id])->number.'/review', $signedIn],
    'review someone else\'s order' => ['post', fn () => '/account/orders/'.Order::factory()->completed()->forCustomer(User::factory()->create())->create()->number.'/review', []],
]);

it('lets people change only their own account records', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy account writes')->with(RoleMatrix::ROLES);

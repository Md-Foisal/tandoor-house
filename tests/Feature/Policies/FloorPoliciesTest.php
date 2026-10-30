<?php

use App\Enums\TableRequestType;
use App\Models\DiningTable;
use App\Models\Reservation;
use Tests\Feature\Policies\RoleMatrix;

dataset('policy floor writes', [
    'change a table status' => ['post', fn () => '/staff/floor/tables/'.DiningTable::factory()->create()->id.'/status', RoleMatrix::FLOOR],
    'transfer a table' => ['post', fn () => '/staff/floor/tables/'.DiningTable::factory()->create()->id.'/transfer', RoleMatrix::FLOOR],
    'merge tables' => ['post', fn () => '/staff/floor/merge', RoleMatrix::FLOOR],
    'unmerge a table' => ['delete', fn () => '/staff/floor/tables/'.DiningTable::factory()->create()->id.'/merge', RoleMatrix::FLOOR],
    'handle a table request' => ['post', fn () => '/staff/floor/requests/'.DiningTable::factory()->create()->requests()->create(['type' => TableRequestType::CallWaiter])->id.'/handle', RoleMatrix::FLOOR],
    'seat a booking' => ['post', fn () => '/staff/floor/reservations/'.Reservation::factory()->create()->id.'/seat', RoleMatrix::FLOOR],
]);

it('guards every floor write by role', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy floor writes')->with(RoleMatrix::ROLES);

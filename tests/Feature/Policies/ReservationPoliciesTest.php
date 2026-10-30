<?php

use App\Models\Reservation;
use Tests\Feature\Policies\RoleMatrix;

dataset('policy reservation writes', [
    'book for a guest' => ['post', fn () => '/staff/reservations', RoleMatrix::FLOOR],
    'move a booking on' => ['post', fn () => '/staff/reservations/'.Reservation::factory()->create()->id.'/status', RoleMatrix::FLOOR],
    'mark a no-show' => ['post', fn () => ['/staff/reservations/'.Reservation::factory()->create()->id.'/status', ['action' => 'no_show']], RoleMatrix::FLOOR],
    'confirm a deposit taken in person' => ['post', fn () => ['/staff/reservations/'.Reservation::factory()->pendingDeposit()->create()->id.'/status', ['action' => 'confirm', 'reason' => 'Card terminal 1234']], RoleMatrix::MANAGEMENT],
    'move a booking to another table' => ['patch', fn () => '/staff/reservations/'.Reservation::factory()->create()->id.'/table', RoleMatrix::FLOOR],
]);

it('guards every reservation board write by role', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy reservation writes')->with(RoleMatrix::ROLES);

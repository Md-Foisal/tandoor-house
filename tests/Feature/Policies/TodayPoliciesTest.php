<?php

use Tests\Feature\Policies\RoleMatrix;

dataset('policy today writes', [
    'clock in' => ['post', fn () => '/staff/clock-in', RoleMatrix::STAFF],
    'clock out' => ['post', fn () => '/staff/clock-out', RoleMatrix::STAFF],
]);

it('lets every member of staff clock themselves in and out', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy today writes')->with(RoleMatrix::ROLES);

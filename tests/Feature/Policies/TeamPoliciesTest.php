<?php

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Tests\Feature\Policies\RoleMatrix;

function policyStaff(Role $role): User
{
    return User::factory()->role($role)->create();
}

dataset('policy team writes', [
    'add a member of staff' => ['post', fn () => '/staff/team/people', RoleMatrix::MANAGEMENT],
    'edit a waiter' => ['patch', fn () => '/staff/team/people/'.policyStaff(Role::Waiter)->id, RoleMatrix::MANAGEMENT],
    'edit another manager' => ['patch', fn () => '/staff/team/people/'.policyStaff(Role::Manager)->id, RoleMatrix::OWNER],
    'remove a waiter' => ['delete', fn () => '/staff/team/people/'.policyStaff(Role::Waiter)->id, RoleMatrix::OWNER],
    'add a shift' => ['post', fn () => '/staff/team/shifts', RoleMatrix::MANAGEMENT],
    'copy a week of shifts' => ['post', fn () => '/staff/team/shifts/copy-week', RoleMatrix::MANAGEMENT],
    'move a waiter\'s shift' => ['patch', fn () => '/staff/team/shifts/'.Shift::factory()->create()->id, RoleMatrix::MANAGEMENT],
    'move a manager\'s shift' => ['patch', fn () => '/staff/team/shifts/'.Shift::factory()->role(Role::Manager)->create()->id, RoleMatrix::OWNER],
    'delete a waiter\'s shift' => ['delete', fn () => '/staff/team/shifts/'.Shift::factory()->create()->id, RoleMatrix::MANAGEMENT],
    'delete a manager\'s shift' => ['delete', fn () => '/staff/team/shifts/'.Shift::factory()->role(Role::Manager)->create()->id, RoleMatrix::OWNER],
    'fix a forgotten clock-out' => ['patch', fn () => '/staff/team/attendance/'.Attendance::create([
        'user_id' => policyStaff(Role::Waiter)->id,
        'clocked_in_at' => now()->subHours(3),
    ])->id, RoleMatrix::MANAGEMENT],
    'export attendance' => ['get', fn () => '/staff/team/attendance/export', RoleMatrix::MANAGEMENT],
    'export tips' => ['get', fn () => '/staff/team/tips/export', RoleMatrix::MANAGEMENT],
    'record tips' => ['post', fn () => '/staff/team/tips/record', RoleMatrix::MANAGEMENT],
    'pay tips out' => ['post', fn () => '/staff/team/tips/pay', RoleMatrix::OWNER],
]);

it('guards every team write by role', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy team writes')->with(RoleMatrix::ROLES);

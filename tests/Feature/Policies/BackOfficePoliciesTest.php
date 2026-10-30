<?php

use Tests\Feature\Policies\RoleMatrix;

dataset('policy back office exports', [
    'export the activity log' => ['get', fn () => '/staff/activity/export', RoleMatrix::MANAGEMENT],
    'download the report' => ['get', fn () => '/staff/reports/pdf', RoleMatrix::MANAGEMENT],
    'export a report section' => ['get', fn () => '/staff/reports/export/overview', RoleMatrix::MANAGEMENT],
]);

it('keeps the activity log and reports for managers and owners', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy back office exports')->with(RoleMatrix::ROLES);

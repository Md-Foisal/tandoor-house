<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\StaffSeeder;

it('creates one demo login for every role', function () {
    $this->seed(StaffSeeder::class);

    foreach (Role::cases() as $role) {
        expect(User::where('email', "{$role->value}@tandoorhouse.test")->first()?->role)->toBe($role);
    }
});

<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One demo login per role, all with the password "password".
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $people = [
            [Role::Owner, 'Anjali Mehra', 'owner@tandoorhouse.test'],
            [Role::Manager, 'Tom Whitaker', 'manager@tandoorhouse.test'],
            [Role::Cashier, 'Priya Nair', 'cashier@tandoorhouse.test'],
            [Role::Waiter, 'Callum Reid', 'waiter@tandoorhouse.test'],
            [Role::Kitchen, 'Rakesh Thapa', 'kitchen@tandoorhouse.test'],
            [Role::Rider, 'Dawit Bekele', 'rider@tandoorhouse.test'],
            [Role::Customer, 'Sophie Turner', 'customer@tandoorhouse.test'],
        ];

        foreach ($people as [$role, $name, $email]) {
            User::factory()->role($role)->create([
                'name' => $name,
                'email' => $email,
            ]);
        }

        User::factory()->count(3)->role(Role::Waiter)->create();
        User::factory()->count(2)->role(Role::Kitchen)->create();
        User::factory()->count(2)->role(Role::Rider)->create();
        User::factory()->count(30)->create();
    }
}

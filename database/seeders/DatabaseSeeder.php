<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            StaffSeeder::class,
            MenuSeeder::class,
            VenueSeeder::class,
            CustomerSeeder::class,
            OrderHistorySeeder::class,
            DemoRiderSeeder::class,
            StockSeeder::class,
            ReservationSeeder::class,
            ReviewSeeder::class,
            PeopleSeeder::class,
        ]);
    }
}

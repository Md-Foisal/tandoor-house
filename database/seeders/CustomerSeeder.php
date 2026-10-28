<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\MenuItem;
use App\Models\PromoCode;
use App\Models\User;
use Database\Seeders\Support\Dice;
use Database\Seeders\Support\EastLondon;
use Database\Seeders\Support\Trading;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Addresses for every customer, the promo codes, and the demo customer's idle basket.
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $this->promos();
        $this->addresses();
        $this->basket();
    }

    private function promos(): void
    {
        PromoCode::factory()->percentOff(1000)->create([
            'code' => 'WELCOME10',
            'description' => '10% off your first order',
        ]);

        PromoCode::factory()->amountOff(500, minimum: 3000)->create([
            'code' => 'TANDOOR5',
            'description' => '£5 off when you spend £30 or more',
            'max_uses' => 500,
        ]);

        PromoCode::factory()->percentOff(1500)->expired()->create([
            'code' => 'SUMMER15',
            'description' => 'Summer special: 15% off',
        ]);
    }

    private function addresses(): void
    {
        $demo = User::where('email', 'customer@tandoorhouse.test')->firstOrFail();

        $demo->addresses()->create([
            'label' => 'Home',
            'line1' => '27 Cheshire Street',
            'line2' => 'Flat 3',
            'city' => 'London',
            'postcode' => 'E2 6EH',
            'instructions' => 'Buzzer 3. Rider can leave it with the neighbour at number 25.',
            'is_default' => true,
        ]);
        $demo->addresses()->create([
            'label' => 'Work',
            'line1' => 'Unit 2, 61 Wilkes Street',
            'city' => 'London',
            'postcode' => 'E1 6QF',
            'instructions' => 'Reception is on the first floor.',
            'is_default' => false,
        ]);

        User::query()
            ->where('role', Role::Customer)
            ->whereKeyNot($demo->id)
            ->get()
            ->each(function (User $customer) {
                $customer->addresses()->create([
                    ...EastLondon::address(),
                    'label' => 'Home',
                    'instructions' => Dice::chance(0.25) ? Dice::pick(['Ring the bell twice', 'Leave with the concierge', 'Side door, down the alley', 'Call on arrival']) : null,
                    'is_default' => true,
                ]);

                if (Dice::chance(0.25)) {
                    $customer->addresses()->create([...EastLondon::address(), 'label' => 'Work', 'is_default' => false]);
                }
            });
    }

    private function basket(): void
    {
        $demo = User::where('email', 'customer@tandoorhouse.test')->firstOrFail();
        $idleSince = Trading::now()->subHours(3);

        $lines = collect([['Butter Chicken', 1], ['Garlic and Coriander Naan', 2]])
            ->map(function (array $entry): array {
                $item = MenuItem::where('name', $entry[0])->firstOrFail();

                return [
                    'key' => substr(sha1(json_encode([$item->id, null, [], null], JSON_THROW_ON_ERROR)), 0, 12),
                    'menu_item_id' => $item->id,
                    'item_variant_id' => null,
                    'modifier_ids' => [],
                    'quantity' => $entry[1],
                    'notes' => null,
                ];
            })
            ->all();

        DB::table('carts')->insert([
            'user_id' => $demo->id,
            'lines' => json_encode($lines, JSON_THROW_ON_ERROR),
            'channel' => 'delivery',
            'created_at' => Trading::stamp($idleSince->subMinutes(12)),
            'updated_at' => Trading::stamp($idleSince),
        ]);
    }
}

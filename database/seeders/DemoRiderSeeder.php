<?php

namespace Database\Seeders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Gives the demo rider today's live runs, so signing in as rider@ shows
 * deliveries to collect and one on the road.
 */
class DemoRiderSeeder extends Seeder
{
    public function run(): void
    {
        $rider = User::where('email', 'rider@tandoorhouse.test')->first();

        if ($rider === null) {
            return;
        }

        Order::query()
            ->where('channel', OrderChannel::Delivery)
            ->whereIn('status', [OrderStatus::Ready, OrderStatus::OutForDelivery])
            ->where('placed_at', '>=', now()->startOfDay())
            ->update(['rider_id' => $rider->id]);
    }
}

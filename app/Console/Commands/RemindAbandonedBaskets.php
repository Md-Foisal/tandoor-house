<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Notifications\Customer\AbandonedBasket;
use App\Support\Automation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('baskets:remind')]
#[Description('Email signed-in customers once about a basket left untouched for two hours')]
class RemindAbandonedBaskets extends Command
{
    private const QUIET_HOURS = 2;

    /** Older baskets are stale; switching the automation on must not mail last week's. */
    private const GIVE_UP_HOURS = 48;

    public function handle(Automation $automation): int
    {
        if (! $automation->enabled(Automation::ABANDONED_CART)) {
            return self::SUCCESS;
        }

        $sent = 0;

        Cart::query()
            ->with('user')
            ->whereNotNull('user_id')
            ->whereNull('reminded_at')
            ->where('updated_at', '<=', now()->subHours(self::QUIET_HOURS))
            ->where('updated_at', '>=', now()->subHours(self::GIVE_UP_HOURS))
            ->where('lines', '!=', '[]')
            ->chunkById(100, function ($carts) use ($automation, &$sent) {
                foreach ($carts as $cart) {
                    $sent += $this->remind($cart, $automation) ? 1 : 0;
                }
            });

        $this->info("Reminded {$sent} customers.");

        return self::SUCCESS;
    }

    private function remind(Cart $cart, Automation $automation): bool
    {
        $user = $cart->user;
        $count = (int) array_sum(array_column($cart->lines, 'quantity'));

        if ($user === null || $count < 1) {
            return false;
        }

        $ordered = $user->orders()->where('placed_at', '>=', $cart->updated_at)->exists();

        // Claiming the row first means a second run, or an overlapping one, cannot send it twice.
        $claimed = Cart::query()->whereKey($cart->id)->whereNull('reminded_at')->update(['reminded_at' => now()]);

        if ($claimed === 0 || $ordered) {
            return false;
        }

        $user->notify(new AbandonedBasket($count));

        $automation->log('automation.basket.reminded', __('automations.customers.abandoned_basket.log', [
            'name' => $user->name,
            'count' => $count,
        ]), $cart, ['user_id' => $user->id, 'items' => $count]);

        return true;
    }
}

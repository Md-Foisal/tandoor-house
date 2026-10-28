<?php

namespace App\Http\Controllers;

use App\Enums\OrderChannel;
use App\Http\Resources\MenuItemResource;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Services\CartService;
use App\Support\Settings;
use Inertia\Inertia;
use Inertia\Response;

class BasketController extends Controller
{
    private const SPECIALS_SHOWN = 3;

    public function __invoke(CartService $cart, Settings $settings): Response
    {
        $summary = $cart->summary();
        $stored = collect($cart->lines())->keyBy('key');

        // The undo toast re-adds a line, so each line carries the ids it was built from.
        $lines = array_map(fn (array $line) => [
            ...$line,
            'item_variant_id' => $stored->get($line['key'])['item_variant_id'] ?? null,
            'modifier_ids' => $stored->get($line['key'])['modifier_ids'] ?? [],
        ], $summary['lines']);

        $table = $summary['table'] !== null ? DiningTable::whereKey((int) $summary['table'])->first() : null;
        $zones = DeliveryZone::where('is_active', true)->get();

        return Inertia::render('basket', [
            'basket' => [...$summary, 'lines' => $lines],
            'channels' => array_map(fn (OrderChannel $channel) => [
                'value' => $channel->value,
                'available' => $channel !== OrderChannel::DineIn || $table !== null,
            ], OrderChannel::cases()),
            'table' => $table?->label,
            'service_rate' => (int) $settings->get('service_charge_rate'),
            'delivery' => [
                'from_fee' => $zones->min('fee'),
                'minimum_order' => $zones->min('minimum_order'),
            ],
            'specials' => $summary['count'] === 0
                ? Inertia::defer(fn () => MenuItemResource::collection(
                    MenuItem::with(['variants', 'modifierGroups.modifiers'])
                        ->where('is_special', true)
                        ->where('is_available', true)
                        ->orderBy('position')
                        ->get()
                        ->reject(fn (MenuItem $item) => $item->variants->isNotEmpty() || $item->modifierGroups->isNotEmpty())
                        ->take(self::SPECIALS_SHOWN)
                        ->values(),
                )->resolve())
                : [],
        ]);
    }
}

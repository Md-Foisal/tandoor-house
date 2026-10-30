<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Models\Cart;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The basket lives on the server: one cart per signed-in customer, or per
 * session for guests. Lines store ids only; prices are always read fresh
 * from the menu, so a price change can never be dodged by an old basket.
 *
 * @phpstan-type CartLine array{key: string, menu_item_id: int, item_variant_id: int|null, modifier_ids: list<int>, quantity: int, notes: string|null}
 */
class CartService
{
    public const MAX_QUANTITY = 20;

    private const GUEST_TOKEN = 'guest_cart';

    private ?Cart $cart = null;

    /** @var array<string, array<string, mixed>> */
    private array $summaries = [];

    public function __construct(
        private readonly Request $request,
        private readonly Pricing $pricing,
    ) {}

    public function cart(): Cart
    {
        if ($this->cart !== null) {
            return $this->cart;
        }

        $user = $this->request->user();
        $session = $this->request->hasSession() ? $this->request->session() : null;

        if ($user === null) {
            $token = $session?->get(self::GUEST_TOKEN);

            if ($token === null && $session !== null) {
                $token = Str::random(40);
                $session->put(self::GUEST_TOKEN, $token);
            }

            return $this->cart = Cart::firstOrNew(['session_id' => $token], ['lines' => []]);
        }

        $this->cart = Cart::firstOrNew(['user_id' => $user->id], ['lines' => []]);

        // Session data survives sign-in (only the id is regenerated), so a
        // basket built as a guest is folded into the customer's on arrival.
        $token = $session?->pull(self::GUEST_TOKEN);

        if ($token !== null) {
            $this->absorb(Cart::where('session_id', $token)->first());
        }

        return $this->cart;
    }

    private function absorb(?Cart $guest): void
    {
        if ($guest === null || $this->cart === null) {
            return;
        }

        $lines = $this->cart->lines ?? [];

        foreach ($guest->lines as $line) {
            $keys = array_column($lines, 'key');

            if (! in_array($line['key'], $keys, true)) {
                $lines[] = $line;

                continue;
            }

            $lines = array_map(
                fn (array $existing) => $existing['key'] === $line['key']
                    ? [...$existing, 'quantity' => min(self::MAX_QUANTITY, $existing['quantity'] + $line['quantity'])]
                    : $existing,
                $lines,
            );
        }

        $this->cart->lines = $lines;
        $this->cart->channel ??= $guest->channel;
        $this->cart->dining_table_id ??= $guest->dining_table_id;
        $this->cart->promo_code ??= $guest->promo_code;
        $this->cart->save();

        $guest->delete();
    }

    /**
     * @param  list<int>  $modifierIds
     */
    public function add(MenuItem $item, ?int $variantId, array $modifierIds, int $quantity, ?string $notes): void
    {
        $item->loadMissing(['variants', 'modifierGroups.modifiers']);

        if (! $item->is_available) {
            throw ValidationException::withMessages(['item' => __('cart.sold_out', ['item' => $item->name])]);
        }

        $this->validateChoices($item, $variantId, $modifierIds);

        sort($modifierIds);
        $notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;
        $key = substr(sha1(json_encode([$item->id, $variantId, $modifierIds, $notes], JSON_THROW_ON_ERROR)), 0, 12);

        $lines = $this->lines();
        $found = false;

        foreach ($lines as $index => $line) {
            if ($line['key'] === $key) {
                $lines[$index]['quantity'] = min(self::MAX_QUANTITY, $line['quantity'] + $quantity);
                $found = true;
            }
        }

        if (! $found) {
            $lines[] = [
                'key' => $key,
                'menu_item_id' => $item->id,
                'item_variant_id' => $variantId,
                'modifier_ids' => $modifierIds,
                'quantity' => min(self::MAX_QUANTITY, $quantity),
                'notes' => $notes,
            ];
        }

        $this->save($lines);
    }

    public function update(string $key, int $quantity): void
    {
        $lines = [];

        foreach ($this->lines() as $line) {
            if ($line['key'] === $key) {
                $line['quantity'] = min(self::MAX_QUANTITY, $quantity);
            }

            if ($line['quantity'] > 0) {
                $lines[] = $line;
            }
        }

        $this->save($lines);
    }

    public function remove(string $key): void
    {
        $this->save(array_values(array_filter(
            $this->lines(),
            fn (array $line) => $line['key'] !== $key,
        )));
    }

    public function clear(): void
    {
        $this->summaries = [];
        $cart = $this->cart();
        $cart->lines = [];
        $cart->promo_code = null;
        $cart->save();
    }

    public function useChannel(OrderChannel $channel, ?DiningTable $table = null): void
    {
        $this->summaries = [];
        $cart = $this->cart();
        $cart->channel = $channel->value;
        $cart->dining_table_id = $table?->id;
        $cart->save();
    }

    public function applyPromo(?string $code): void
    {
        $this->summaries = [];
        $cart = $this->cart();
        $cart->promo_code = $code !== null ? strtoupper(trim($code)) : null;
        $cart->save();
    }

    public function count(): int
    {
        return array_sum(array_column($this->lines(), 'quantity'));
    }

    /**
     * Lines with names, prices and allergens resolved from the menu, plus
     * totals for the cart's channel.
     *
     * @return array<string, mixed>
     */
    public function summary(?int $deliveryFee = null, int $tip = 0): array
    {
        return $this->summaries[$deliveryFee.'|'.$tip] ??= $this->summarise($deliveryFee, $tip);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(?int $deliveryFee, int $tip): array
    {
        $cart = $this->cart();
        $lines = collect($this->lines());
        $items = MenuItem::with(['variants', 'modifierGroups.modifiers'])
            ->findMany($lines->pluck('menu_item_id'))
            ->keyBy('id');

        $priced = $lines
            ->filter(fn (array $line) => $items->has($line['menu_item_id']))
            ->map(fn (array $line) => $this->price($line, $items->get($line['menu_item_id'])))
            ->values();

        $channel = OrderChannel::tryFrom((string) $cart->channel) ?? OrderChannel::Delivery;
        $promo = $this->promo();
        $pricedLines = $priced->map(fn (array $line) => ['total' => $line['total'], 'vat_rate' => $line['vat_rate']])->all();
        $totals = $this->pricing->totals(
            $pricedLines,
            $channel,
            null,
            $promo,
            $tip,
        );

        if ($deliveryFee !== null && $channel === OrderChannel::Delivery) {
            $totals['delivery_fee'] = $deliveryFee;
            $totals['total'] += $deliveryFee;
        }

        $reward = $this->pricing->loyaltyReward($this->request->user(), $totals['subtotal']);

        return [
            'lines' => $priced->all(),
            'count' => (int) $priced->sum('quantity'),
            'channel' => $channel->value,
            'table' => $cart->dining_table_id,
            'promo_code' => $promo?->code,
            'promo_invalid' => $cart->promo_code !== null && $promo === null,
            'has_unavailable' => $priced->contains(fn (array $line) => ! $line['is_available']),
            'has_alcohol' => $priced->contains(fn (array $line) => $line['is_alcoholic']),
            // VAT with the reward applied, so the checkout can show it without a round trip.
            'loyalty' => $reward === null ? null : [
                ...$reward,
                'vat_total' => $this->pricing->vat($pricedLines, $totals['subtotal'], $reward['discount']),
            ],
            ...$totals,
        ];
    }

    /**
     * @return list<CartLine>
     */
    public function lines(): array
    {
        return $this->cart()->lines ?? [];
    }

    /**
     * @param  list<CartLine>  $lines
     */
    private function save(array $lines): void
    {
        $this->summaries = [];
        $cart = $this->cart();
        $cart->lines = $lines;
        $cart->reminded_at = null;
        // Carts do not use Eloquent timestamps; baskets:remind reads this as "last touched".
        $cart->setAttribute('updated_at', now()->toDateTimeString());
        $cart->save();
    }

    private function promo(): ?PromoCode
    {
        $code = $this->cart()->promo_code;

        if ($code === null) {
            return null;
        }

        return PromoCode::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($query) => $query->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses'))
            ->first();
    }

    /**
     * @param  CartLine  $line
     * @return array<string, mixed>
     */
    private function price(array $line, MenuItem $item): array
    {
        $variant = $item->variants->firstWhere('id', $line['item_variant_id']);
        /** @var Collection<int, Modifier> $modifiers */
        $modifiers = $item->modifierGroups
            ->flatMap(fn ($group) => $group->modifiers)
            ->whereIn('id', $line['modifier_ids'])
            ->values();

        $unit = ($variant->price ?? $item->price) + (int) $modifiers->sum('price');
        $allergens = $item->allergens
            ->merge($modifiers->flatMap(fn (Modifier $modifier) => $modifier->allergens ?? []))
            ->map(fn ($allergen) => $allergen->value)
            ->unique()
            ->values();

        return [
            'key' => $line['key'],
            'menu_item_id' => $item->id,
            'slug' => $item->slug,
            'name' => $item->name,
            'variant' => $variant?->name,
            'modifiers' => $modifiers->map(fn (Modifier $modifier) => ['name' => $modifier->name, 'price' => $modifier->price])->all(),
            'notes' => $line['notes'],
            'quantity' => $line['quantity'],
            'unit_price' => $unit,
            'total' => $unit * $line['quantity'],
            'vat_rate' => $item->vat_rate,
            'allergens' => $allergens->all(),
            'image' => $item->imagePayload(),
            'is_available' => $item->is_available,
            'is_alcoholic' => $item->is_alcoholic,
        ];
    }

    /**
     * @param  list<int>  $modifierIds
     */
    private function validateChoices(MenuItem $item, ?int $variantId, array $modifierIds): void
    {
        if ($item->variants->isNotEmpty() && $item->variants->firstWhere('id', $variantId) === null) {
            throw ValidationException::withMessages(['variant' => __('cart.choose_size')]);
        }

        if ($item->variants->isEmpty() && $variantId !== null) {
            throw ValidationException::withMessages(['variant' => __('cart.invalid_choice')]);
        }

        $known = $item->modifierGroups->flatMap(fn ($group) => $group->modifiers->pluck('id'));

        if (collect($modifierIds)->diff($known)->isNotEmpty()) {
            throw ValidationException::withMessages(['modifiers' => __('cart.invalid_choice')]);
        }

        foreach ($item->modifierGroups as $group) {
            $chosen = $group->modifiers->whereIn('id', $modifierIds)->count();

            if ($chosen < $group->min_select || $chosen > $group->max_select) {
                throw ValidationException::withMessages([
                    'modifiers' => __('cart.group_limits', [
                        'group' => $group->name,
                        'min' => $group->min_select,
                        'max' => $group->max_select,
                    ]),
                ]);
            }
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\FindDeliveryZone;
use App\Actions\PickupSlots;
use App\Actions\PlaceOrder;
use App\Enums\OrderChannel;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Http\Requests\Checkout\PostcodeRequest;
use App\Http\Requests\Checkout\PromoRequest;
use App\Models\DiningTable;
use App\Models\PromoCode;
use App\Services\CartService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(Request $request, PickupSlots $slots, Settings $settings): Response|RedirectResponse
    {
        $summary = $this->cart->summary();

        if ($summary['count'] === 0) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('checkout.empty_flash')]);

            return redirect()->route('basket');
        }

        $user = $request->user();
        $table = $summary['table'] !== null ? DiningTable::query()->whereKey($summary['table'])->first() : null;
        $channels = [OrderChannel::Delivery->value, OrderChannel::Pickup->value];

        if ($table !== null) {
            $channels[] = OrderChannel::DineIn->value;
        }

        $chosen = $summary['channel'];

        return Inertia::render('checkout', [
            'summary' => $this->present($summary),
            'channels' => $channels,
            'channel' => in_array($chosen, $channels, true) ? $chosen : $channels[0],
            'table' => $table?->label,
            'pickup' => $slots->handle(),
            'addresses' => $user?->addresses()->orderByDesc('is_default')->orderBy('label')->get()
                ->map(fn ($address) => [
                    'id' => $address->id,
                    'label' => $address->label,
                    'line1' => $address->line1,
                    'line2' => $address->line2,
                    'city' => $address->city,
                    'postcode' => $address->postcode,
                    'instructions' => $address->instructions,
                    'is_default' => $address->is_default,
                ])->all() ?? [],
            'contact' => [
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'phone' => $user->phone ?? '',
                'marketing_opt_in' => false,
            ],
            'tipPresets' => array_values((array) $settings->get('tip_presets')),
            'serviceChargeRate' => (int) $settings->get('service_charge_rate'),
        ]);
    }

    public function promo(PromoRequest $request): RedirectResponse
    {
        $code = strtoupper(trim((string) $request->input('code')));

        if ($code === '') {
            $this->cart->applyPromo(null);

            return back();
        }

        $promo = PromoCode::query()->where('code', $code)->first();
        $subtotal = $this->cart->summary()['subtotal'];

        $error = match (true) {
            $promo === null || ! $promo->is_active => __('checkout.promo.unknown'),
            ($promo->starts_at?->isFuture() ?? false) || ($promo->ends_at?->isPast() ?? false) => __('checkout.promo.expired'),
            $promo->max_uses !== null && $promo->uses >= $promo->max_uses => __('checkout.promo.used_up'),
            $subtotal < $promo->minimum_order => __('checkout.promo.minimum', [
                'short' => Money::format((int) ($promo->minimum_order - $subtotal)),
            ]),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['code' => $error]);
        }

        $this->cart->applyPromo($code);

        return back();
    }

    public function postcode(PostcodeRequest $request, FindDeliveryZone $zones): JsonResponse
    {
        $postcode = $request->string('postcode')->toString();
        $zone = $zones->handle($postcode);

        if ($zone === null) {
            return response()->json([
                'postcode' => $postcode,
                'covered' => false,
                'message' => __('checkout.postcode.outside', ['postcode' => $postcode]),
            ]);
        }

        $subtotal = (int) $this->cart->summary()['subtotal'];
        $short = max(0, $zone->minimum_order - $subtotal);

        return response()->json([
            'postcode' => $postcode,
            'covered' => true,
            'zone' => [
                'id' => $zone->id,
                'name' => $zone->name,
                'fee' => $zone->fee,
                'minimum' => $zone->minimum_order,
                'eta_minutes' => $zone->eta_minutes,
            ],
            'short_by' => $short,
            'message' => $short > 0
                ? __('checkout.postcode.below_minimum', ['short' => Money::format($short), 'minimum' => Money::format($zone->minimum_order)])
                : null,
        ]);
    }

    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $channel = OrderChannel::from($request->string('channel')->toString());
        $table = $channel === OrderChannel::DineIn ? DiningTable::query()->whereKey($this->cart->summary()['table'])->first() : null;

        $this->cart->useChannel($channel, $table);

        $order = $placeOrder->handle($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('checkout.placed', ['number' => $order->number])]);

        return redirect()->route('orders.show', $order->number);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function present(array $summary): array
    {
        return [
            ...$summary,
            'lines' => array_map(fn (array $line) => [
                'key' => $line['key'],
                'name' => $line['name'],
                'variant' => $line['variant'],
                'modifiers' => $line['modifiers'],
                'notes' => $line['notes'],
                'quantity' => $line['quantity'],
                'total' => $line['total'],
                'allergens' => $line['allergens'],
                'is_available' => $line['is_available'],
            ], $summary['lines']),
        ];
    }
}

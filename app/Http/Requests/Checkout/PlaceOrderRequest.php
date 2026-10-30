<?php

namespace App\Http\Requests\Checkout;

use App\Actions\FindDeliveryZone;
use App\Actions\PickupSlots;
use App\Actions\UkFormats;
use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Services\CartService;
use App\Services\OpeningHours;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        $user = $this->user();

        if ($user !== null && $this->filled('address_id')) {
            $address = Address::query()->whereBelongsTo($user)->whereKey((int) $this->input('address_id'))->first();

            if ($address !== null) {
                $merge = [
                    'line1' => $this->input('line1', $address->line1),
                    'line2' => $this->input('line2', $address->line2),
                    'city' => $this->input('city', $address->city),
                    'postcode' => $this->input('postcode', $address->postcode),
                    'instructions' => $this->input('instructions', $address->instructions),
                ];
            }
        }

        $postcode = $merge['postcode'] ?? $this->input('postcode');
        $phone = $this->input('phone');

        $this->merge([
            ...$merge,
            'postcode' => is_string($postcode) ? (UkFormats::postcode($postcode) ?? $postcode) : null,
            'phone' => is_string($phone) ? (UkFormats::mobile($phone) ?? $phone) : null,
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $delivery = $this->input('channel') === OrderChannel::Delivery->value;

        return [
            'channel' => ['required', Rule::enum(OrderChannel::class)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'phone' => ['required', 'regex:/^\+447[1-57-9]\d{8}$/'],
            'marketing_opt_in' => ['boolean'],
            'address_id' => ['nullable', 'integer'],
            'line1' => [Rule::requiredIf($delivery), 'nullable', 'string', 'max:120'],
            'line2' => ['nullable', 'string', 'max:120'],
            'city' => [Rule::requiredIf($delivery), 'nullable', 'string', 'max:80'],
            'postcode' => [Rule::requiredIf($delivery), 'nullable', 'string', 'max:10'],
            'instructions' => ['nullable', 'string', 'max:200'],
            'pickup_at' => ['nullable', 'date'],
            'tip' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'remove_service_charge' => ['boolean'],
            'use_loyalty' => ['boolean'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'age_confirmed' => ['boolean'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkOrder($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('checkout.errors.name'),
            'email.required' => __('checkout.errors.email'),
            'email.email' => __('checkout.errors.email'),
            'phone.required' => __('checkout.errors.phone'),
            'phone.regex' => __('checkout.errors.phone'),
            'line1.required' => __('checkout.errors.line1'),
            'city.required' => __('checkout.errors.city'),
            'postcode.required' => __('checkout.errors.postcode'),
            'channel.required' => __('checkout.errors.channel'),
            'payment_method.required' => __('checkout.errors.payment_method'),
        ];
    }

    private function checkOrder(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $cart = app(CartService::class);
        $summary = $cart->summary();
        $channel = OrderChannel::from($this->string('channel')->toString());
        $slots = app(PickupSlots::class);

        if ($summary['count'] === 0) {
            $validator->errors()->add('cart', __('checkout.errors.empty'));

            return;
        }

        if ($summary['has_unavailable']) {
            $names = implode(', ', array_column(array_filter($summary['lines'], fn (array $line) => ! $line['is_available']), 'name'));
            $validator->errors()->add('cart', __('checkout.errors.unavailable', ['items' => $names]));
        }

        if ($this->boolean('use_loyalty')) {
            if (($summary['loyalty']['discount'] ?? 0) < 1) {
                $validator->errors()->add('use_loyalty', __('checkout.errors.loyalty_unavailable'));
            } elseif ($summary['promo_code'] !== null) {
                $validator->errors()->add('use_loyalty', __('checkout.errors.loyalty_with_promo'));
            }
        }

        if ($summary['has_alcohol'] && ! $this->boolean('age_confirmed')) {
            $validator->errors()->add('age_confirmed', __('checkout.errors.age'));
        }

        $this->checkChannel($validator, $channel, $summary, $slots);
        $this->checkPayment($validator, $channel);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function checkChannel(Validator $validator, OrderChannel $channel, array $summary, PickupSlots $slots): void
    {
        if ($channel === OrderChannel::DineIn && $summary['table'] === null) {
            $validator->errors()->add('channel', __('checkout.errors.no_table'));
        }

        if ($channel === OrderChannel::Delivery) {
            $postcode = UkFormats::postcode($this->string('postcode')->toString());
            $zone = $postcode !== null ? app(FindDeliveryZone::class)->handle($postcode) : null;

            if ($postcode === null) {
                $validator->errors()->add('postcode', __('checkout.errors.postcode'));
            } elseif ($zone === null) {
                $validator->errors()->add('postcode', __('checkout.postcode.outside', ['postcode' => $postcode]));
            } elseif ($summary['subtotal'] < $zone->minimum_order) {
                $validator->errors()->add('cart', __('checkout.errors.minimum', [
                    'minimum' => Money::format($zone->minimum_order),
                    'short' => Money::format($zone->minimum_order - $summary['subtotal']),
                ]));
            }
        }

        if ($channel === OrderChannel::Pickup && $this->filled('pickup_at')) {
            if (! $slots->isValid(CarbonImmutable::parse($this->string('pickup_at')->toString()))) {
                $validator->errors()->add('pickup_at', __('checkout.errors.slot'));
            }

            return;
        }

        if ($channel !== OrderChannel::DineIn && ! $slots->asapAvailable()) {
            $validator->errors()->add('pickup_at', __('checkout.errors.closed'));
        }

        if ($channel === OrderChannel::DineIn && ! app(OpeningHours::class)->isOpen()) {
            $validator->errors()->add('channel', __('checkout.errors.closed_table'));
        }
    }

    private function checkPayment(Validator $validator, OrderChannel $channel): void
    {
        $method = PaymentMethod::from($this->string('payment_method')->toString());

        $allowed = match ($channel) {
            OrderChannel::Delivery => $method !== PaymentMethod::PayAtVenue,
            OrderChannel::Pickup, OrderChannel::DineIn => $method !== PaymentMethod::Cash,
        };

        if (! $allowed) {
            $validator->errors()->add('payment_method', __('checkout.errors.payment_method'));
        }
    }
}

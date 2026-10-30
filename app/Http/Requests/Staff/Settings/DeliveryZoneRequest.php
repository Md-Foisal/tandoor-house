<?php

namespace App\Http\Requests\Staff\Settings;

use App\Models\DeliveryZone;
use Illuminate\Validation\Validator;

class DeliveryZoneRequest extends SettingsRequest
{
    private const OUTWARD = '/^[A-Z]{1,2}[0-9][A-Z0-9]?$/';

    protected function prepareForValidation(): void
    {
        $prefixes = $this->input('prefixes');

        if (is_string($prefixes)) {
            $prefixes = preg_split('/[\s,]+/', $prefixes, -1, PREG_SPLIT_NO_EMPTY);
        }

        if (is_array($prefixes)) {
            $this->merge(['prefixes' => array_values(array_unique(array_map(
                fn ($p) => strtoupper(preg_replace('/\s+/', '', (string) $p) ?? ''),
                $prefixes,
            )))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'prefixes' => ['required', 'array', 'min:1', 'max:40'],
            'prefixes.*' => ['required', 'string'],
            'fee' => ['required', 'integer', 'min:0', 'max:5000'],
            'minimum_order' => ['required', 'integer', 'min:0', 'max:20000'],
            'eta_minutes' => ['required', 'integer', 'min:5', 'max:180'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->input('prefixes', []) as $i => $prefix) {
                if (! preg_match(self::OUTWARD, (string) $prefix)) {
                    $validator->errors()->add("prefixes.{$i}", __('staff_settings.zones.errors.outward', ['code' => $prefix]));
                }
            }

            $zone = $this->route('zone');
            $ignore = $zone instanceof DeliveryZone ? $zone->id : 0;
            $taken = DeliveryZone::query()->whereKeyNot($ignore)->where('is_active', true)->get();

            if (! $this->boolean('is_active') || $validator->errors()->has('prefixes')) {
                return;
            }

            foreach ((array) $this->input('prefixes', []) as $prefix) {
                $clash = $taken->first(fn (DeliveryZone $other) => in_array($prefix, $other->postcode_prefixes, true));

                if ($clash !== null) {
                    $validator->errors()->add('prefixes', __('staff_settings.zones.errors.clash', ['code' => $prefix, 'zone' => $clash->name]));

                    return;
                }
            }
        }];
    }
}

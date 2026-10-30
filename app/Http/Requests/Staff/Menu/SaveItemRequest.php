<?php

namespace App\Http\Requests\Staff\Menu;

use App\Enums\Allergen;
use App\Enums\ServicePeriod;
use App\Enums\Station;
use App\Models\MenuItem;
use App\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItemRequest extends FormRequest
{
    public const VAT_RATES = [0, 2000];

    /**
     * Only the owner may change a VAT rate; others keep the current one
     * (or the house default on a new dish).
     */
    public function authorize(): bool
    {
        $item = $this->route('item');
        $allowed = $item instanceof MenuItem ? $this->user()?->can('update', $item) : $this->user()?->can('create', MenuItem::class);

        if (! ($allowed ?? false)) {
            return false;
        }

        if ($this->user()->can('setVatRate', MenuItem::class) || ! $this->has('vat_rate')) {
            return true;
        }

        return $this->integer('vat_rate') === $this->currentVatRate();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $item = $item instanceof MenuItem ? $item : null;
        $groupIds = $item === null ? [0] : ($item->modifierGroups()->pluck('id')->all() ?: [0]);

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('menu_items', 'slug')->ignore($item?->id)],
            'description' => ['required', 'string', 'max:1000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'integer', 'min:0', 'max:100000'],
            'vat_rate' => ['required', 'integer', Rule::in(self::VAT_RATES)],
            'kcal' => ['required', 'integer', 'min:0', 'max:5000'],
            'spice_level' => ['required', 'integer', 'min:0', 'max:3'],
            'is_vegetarian' => ['required', 'boolean'],
            'is_vegan' => ['required', 'boolean'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['distinct', Rule::enum(Allergen::class)],
            'allergens_checked' => ['accepted'],
            'station' => ['required', Rule::enum(Station::class)],
            'prep_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'is_hot' => ['required', 'boolean'],
            'is_alcoholic' => ['required', 'boolean'],
            'is_special' => ['required', 'boolean'],
            'is_available' => ['required', 'boolean'],
            'service_periods' => ['nullable', 'array'],
            'service_periods.*' => ['distinct', Rule::enum(ServicePeriod::class)],
            'remove_photo' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120', 'dimensions:min_width=800,min_height=600'],

            'variants' => ['nullable', 'array', 'max:8'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('item_variants', 'id')->where('menu_item_id', $item instanceof MenuItem ? $item->id : 0)],
            'variants.*.name' => ['required', 'string', 'max:60'],
            'variants.*.price' => ['required', 'integer', 'min:0', 'max:100000'],
            'variants.*.kcal' => ['nullable', 'integer', 'min:0', 'max:5000'],

            'modifier_groups' => ['nullable', 'array', 'max:8'],
            'modifier_groups.*.id' => ['nullable', 'integer', Rule::exists('modifier_groups', 'id')->where('menu_item_id', $item instanceof MenuItem ? $item->id : 0)],
            'modifier_groups.*.name' => ['required', 'string', 'max:60'],
            'modifier_groups.*.min' => ['required', 'integer', 'min:0', 'max:20'],
            'modifier_groups.*.max' => ['required', 'integer', 'min:1', 'max:20', 'gte:modifier_groups.*.min'],
            'modifier_groups.*.modifiers' => ['required', 'array', 'min:1', 'max:30'],
            'modifier_groups.*.modifiers.*.id' => ['nullable', 'integer', Rule::exists('modifiers', 'id')->whereIn('modifier_group_id', $groupIds)],
            'modifier_groups.*.modifiers.*.name' => ['required', 'string', 'max:60'],
            'modifier_groups.*.modifiers.*.price' => ['required', 'integer', 'min:0', 'max:100000'],
            'modifier_groups.*.modifiers.*.allergens' => ['nullable', 'array'],
            'modifier_groups.*.modifiers.*.allergens.*' => ['distinct', Rule::enum(Allergen::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allergens_checked.accepted' => __('staff_menu.errors.allergens_unchecked'),
            'modifier_groups.*.max.gte' => __('staff_menu.errors.max_below_min'),
            'modifier_groups.*.modifiers.required' => __('staff_menu.errors.group_needs_modifier'),
            'modifier_groups.*.modifiers.min' => __('staff_menu.errors.group_needs_modifier'),
            'photo.dimensions' => __('staff_menu.errors.photo_small'),
            'photo.max' => __('staff_menu.errors.photo_large'),
            'photo.mimes' => __('staff_menu.errors.photo_type'),
            'photo.mimetypes' => __('staff_menu.errors.photo_type'),
        ];
    }

    private function currentVatRate(): int
    {
        $item = $this->route('item');

        return $item instanceof MenuItem
            ? $item->vat_rate
            : (int) app(Settings::class)->get('vat_rate', 2000);
    }
}

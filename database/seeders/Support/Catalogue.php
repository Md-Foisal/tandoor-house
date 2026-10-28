<?php

namespace Database\Seeders\Support;

use App\Models\MenuItem;
use Illuminate\Support\Collection;

/**
 * Picks realistic order lines from the seeded menu: variants replace the
 * dish price, and the unit price includes chosen modifiers, as the cart does.
 *
 * @phpstan-type Line array{menu_item_id: int, item_variant_id: int|null, name: string, variant_name: string|null, modifiers: list<array{name: string, price: int}>, unit_price: int, quantity: int, vat_rate: int, station: string, notes: string|null, prep: int, alcohol: bool}
 */
final class Catalogue
{
    private const POPULAR = [
        'Butter Naan' => 12, 'Butter Chicken' => 10, 'Chicken Tikka' => 8, 'Garlic and Coriander Naan' => 8,
        'Steamed Basmati' => 7, 'Poppadoms and Pickles' => 7, 'Hyderabadi Lamb Biryani' => 6,
        'House Black Daal' => 6, 'Mango Lassi' => 6, 'Lamb Rogan Josh' => 5, 'Samosa Chaat' => 5,
        'Onion Bhaji' => 4, 'Palak Paneer' => 4, 'Jeera Rice' => 4, 'Lamb Seekh Kebab' => 4,
        'Indian Pale Ale' => 4, 'Cucumber Raita' => 4, 'Chicken Chettinad' => 3, 'Tandoori Roti' => 3,
    ];

    private const NOTES = [
        'No coriander', 'Extra crispy', 'Mild please', 'Extra hot', 'Sauce on the side', 'No onions',
        'Well done', 'Cut in half', 'Nut allergy, please check', 'Not too oily',
    ];

    /** @var Collection<int, MenuItem> */
    private Collection $items;

    public function __construct()
    {
        $this->items = MenuItem::query()->with(['category', 'variants', 'modifierGroups.modifiers'])->where('is_available', true)->get();
    }

    /**
     * @return Line
     */
    public function line(?MenuItem $item = null): array
    {
        $item ??= $this->weightedItem();
        $variant = $item->variants->isNotEmpty() ? $item->variants->random() : null;
        $modifiers = [];

        foreach ($item->modifierGroups as $group) {
            $count = $group->min_select > 0
                ? mt_rand($group->min_select, max($group->min_select, $group->max_select))
                : (Dice::chance(0.3) ? 1 : 0);

            foreach ($group->modifiers->shuffle()->take($count) as $modifier) {
                $modifiers[] = ['name' => $modifier->name, 'price' => $modifier->price];
            }
        }

        $unit = ($variant->price ?? $item->price) + array_sum(array_column($modifiers, 'price'));
        $isSide = in_array($item->category->slug ?? '', ['breads', 'sides-chutneys', 'drinks'], true);

        return [
            'menu_item_id' => $item->id,
            'item_variant_id' => $variant?->id,
            'name' => $item->name,
            'variant_name' => $variant?->name,
            'modifiers' => $modifiers,
            'unit_price' => $unit,
            'quantity' => Dice::weighted([[1, $isSide ? 60 : 82], [2, $isSide ? 32 : 15], [3, $isSide ? 8 : 3]]),
            'vat_rate' => $item->vat_rate,
            'station' => $item->station->value,
            'notes' => Dice::chance(0.13) ? Dice::pick(self::NOTES) : null,
            'prep' => $item->prep_minutes,
            'alcohol' => $item->is_alcoholic,
        ];
    }

    /**
     * @return list<Line>
     */
    public function lines(int $count): array
    {
        $lines = [];
        $used = [];

        while (count($lines) < $count && count($used) < $this->items->count()) {
            $line = $this->line();

            if (isset($used[$line['menu_item_id']])) {
                continue;
            }

            $used[$line['menu_item_id']] = true;
            $lines[] = $line;
        }

        return $lines;
    }

    public function lineCount(bool $dineIn): int
    {
        $count = Dice::weighted([[1, 10], [2, 25], [3, 30], [4, 20], [5, 10], [6, 5]]);

        return min(6, $count + ($dineIn && Dice::chance(0.5) ? 1 : 0));
    }

    private function weightedItem(): MenuItem
    {
        /** @var non-empty-list<array{0: MenuItem, 1: int}> $options */
        $options = $this->items
            ->map(fn (MenuItem $item) => [$item, self::POPULAR[$item->name] ?? 3])
            ->values()
            ->all();

        return Dice::weighted($options);
    }
}

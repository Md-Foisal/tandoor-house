<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\Dice;
use Database\Seeders\Support\Trading;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Suppliers, ingredients, a recipe for every dish, and sixty days of stock
 * movements worked out from the orders already seeded: sales deduct what
 * the recipes use, scheduled deliveries top shelves back up, and waste is
 * logged now and then.
 */
class StockSeeder extends Seeder
{
    /** @var list<array{string, string, string, string, string, int}> */
    private const SUPPLIERS = [
        ['Southall Spice & Grain', 'Harpreet Dhillon', 'orders@southallspice.test', '020 8574 3310', '3', 0],
        ['Whitechapel Halal Meats', 'Yusuf Rahman', 'yusuf@whitechapelhalal.test', '020 7247 8812', '1', 0],
        ['Lea Valley Dairy', 'Helen Ashworth', 'orders@leavalleydairy.test', '020 8556 2201', '1', 0],
        ['New Spitalfields Produce', 'Terry Brennan', 'terry@newspitalfieldsproduce.test', '020 8556 9040', '1', 0],
        ['Billingsgate Fish Co.', 'Dean Cooper', 'dean@billingsgatefish.test', '020 7987 1160', '1', 0],
        ['Hackney Drinks Wholesale', 'Marcus Bell', 'trade@hackneydrinks.test', '020 8985 4471', '2', 0],
    ];

    /**
     * Key => name, unit, supplier index (null for the bakery round), pence per 1000 units, par level.
     *
     * @var array<string, array{0: string, 1: string, 2: int|null, 3: int, 4: int}>
     */
    private const INGREDIENTS = [
        'garam' => ['Garam masala', 'g', 0, 1800, 2000],
        'kashmiri' => ['Kashmiri chilli powder', 'g', 0, 1600, 2000],
        'turmeric' => ['Turmeric', 'g', 0, 900, 1500],
        'cumin' => ['Cumin seed', 'g', 0, 1100, 1500],
        'mustard_seed' => ['Mustard seed', 'g', 0, 700, 1000],
        'chaat' => ['Chaat masala', 'g', 0, 2000, 800],
        'fenugreek' => ['Kasuri methi', 'g', 0, 2400, 500],
        'cardamom' => ['Green cardamom', 'g', 0, 4500, 400],
        'basmati' => ['Aged basmati rice', 'g', 0, 380, 25000],
        'urad' => ['Whole black urad', 'g', 0, 420, 8000],
        'chickpeas' => ['Chickpeas, dried', 'g', 0, 260, 8000],
        'gram_flour' => ['Gram flour', 'g', 0, 240, 5000],
        'atta' => ['Wholewheat atta', 'g', 0, 160, 15000],
        'flour' => ['Strong white flour', 'g', 0, 90, 25000],
        'poppadom' => ['Poppadoms', 'pc', 0, 12000, 600],
        'jackfruit' => ['Young jackfruit, tinned', 'g', 0, 450, 6000],
        'coconut' => ['Coconut milk', 'ml', 0, 320, 8000],
        'cashew' => ['Cashew nuts', 'g', 0, 1500, 3000],
        'pistachio' => ['Pistachios', 'g', 0, 2400, 1000],
        'tamarind' => ['Tamarind paste', 'g', 0, 700, 2000],
        'tea' => ['Assam tea', 'g', 0, 1500, 1500],
        'puri' => ['Pani puri shells', 'pc', 0, 6000, 400],
        'sourdough' => ['Sourdough slices', 'pc', null, 35000, 40],
        'chicken' => ['Chicken thigh, boneless', 'g', 1, 650, 20000],
        'lamb_shoulder' => ['Lamb shoulder', 'g', 1, 1250, 15000],
        'lamb_chops' => ['Welsh lamb chops', 'g', 1, 2400, 8000],
        'lamb_mince' => ['Lamb mince', 'g', 1, 1050, 8000],
        'yoghurt' => ['Greek-style yoghurt', 'g', 2, 280, 15000],
        'paneer' => ['Paneer', 'g', 2, 1100, 8000],
        'butter' => ['Unsalted butter', 'g', 2, 750, 5000],
        'ghee' => ['Ghee', 'g', 2, 1100, 4000],
        'cream' => ['Double cream', 'ml', 2, 480, 8000],
        'milk' => ['Whole milk', 'ml', 2, 110, 20000],
        'cheddar' => ['Mature cheddar', 'g', 2, 900, 4000],
        'onion' => ['Onions', 'g', 3, 90, 25000],
        'tomato' => ['Tomatoes', 'g', 3, 220, 15000],
        'ginger' => ['Ginger', 'g', 3, 350, 3000],
        'garlic' => ['Garlic', 'g', 3, 600, 2500],
        'green_chilli' => ['Green chillies', 'g', 3, 800, 1500],
        'coriander' => ['Fresh coriander', 'g', 3, 1800, 1500],
        'mint' => ['Fresh mint', 'g', 3, 2200, 800],
        'potato' => ['New potatoes', 'g', 3, 130, 15000],
        'spinach' => ['Spinach', 'g', 3, 400, 4000],
        'broccoli' => ['Tenderstem broccoli', 'g', 3, 800, 4000],
        'cucumber' => ['Cucumber', 'g', 3, 150, 5000],
        'limes' => ['Limes', 'pc', 3, 25000, 200],
        'mango' => ['Alphonso mango pulp', 'g', 3, 900, 6000],
        'curry_leaf' => ['Curry leaves', 'g', 3, 4000, 300],
        'prawns' => ['King prawns', 'g', 4, 2200, 6000],
        'hake' => ['Cornish hake', 'g', 4, 2600, 5000],
        'ipa' => ['Indian pale ale, 330 ml', 'pc', 5, 160000, 96],
        'soda' => ['Soda water', 'ml', 5, 70, 20000],
        'sugar' => ['Caster sugar', 'g', 5, 100, 10000],
        'oil' => ['Rapeseed oil', 'ml', 5, 180, 20000],
    ];

    /**
     * Grams, millilitres or pieces per portion.
     *
     * @var array<string, array<string, int>>
     */
    private const RECIPES = [
        'Bhel Puri' => ['gram_flour' => 40, 'tamarind' => 15, 'onion' => 30, 'tomato' => 30, 'coriander' => 5, 'green_chilli' => 3],
        'Pani Puri' => ['puri' => 6, 'potato' => 60, 'chickpeas' => 30, 'mint' => 8, 'tamarind' => 15, 'cumin' => 2],
        'Samosa Chaat' => ['flour' => 40, 'potato' => 80, 'chickpeas' => 40, 'yoghurt' => 40, 'tamarind' => 15, 'oil' => 40, 'chaat' => 2],
        'Onion Bhaji' => ['onion' => 120, 'gram_flour' => 50, 'cumin' => 2, 'curry_leaf' => 1, 'oil' => 60, 'coriander' => 5],
        'Chilli Cheese Toast' => ['sourdough' => 2, 'cheddar' => 80, 'green_chilli' => 8, 'coriander' => 5, 'butter' => 10],
        'Prawn Koliwada' => ['prawns' => 150, 'gram_flour' => 30, 'mustard_seed' => 2, 'kashmiri' => 3, 'oil' => 80, 'limes' => 1],
        'Chicken Tikka' => ['chicken' => 220, 'yoghurt' => 60, 'kashmiri' => 4, 'oil' => 15, 'ginger' => 8, 'garlic' => 8, 'onion' => 20],
        'Lamb Seekh Kebab' => ['lamb_mince' => 180, 'ginger' => 10, 'green_chilli' => 6, 'garam' => 3, 'coriander' => 6, 'onion' => 25],
        'Tandoori Lamb Chops' => ['lamb_chops' => 270, 'yoghurt' => 40, 'garam' => 4, 'ginger' => 8, 'garlic' => 8, 'limes' => 1],
        'Paneer Shashlik' => ['paneer' => 150, 'onion' => 50, 'tomato' => 40, 'yoghurt' => 40, 'mustard_seed' => 2, 'kashmiri' => 3],
        'Tandoori Broccoli' => ['broccoli' => 180, 'cashew' => 25, 'cream' => 30, 'cardamom' => 1, 'chaat' => 2],
        'Malai Fish Tikka' => ['hake' => 200, 'cream' => 40, 'yoghurt' => 30, 'cardamom' => 1, 'ginger' => 6],
        'House Black Daal' => ['urad' => 80, 'butter' => 20, 'cream' => 40, 'tomato' => 60, 'garlic' => 8, 'ginger' => 8],
        'Butter Chicken' => ['chicken' => 200, 'tomato' => 150, 'butter' => 40, 'cream' => 50, 'fenugreek' => 1, 'kashmiri' => 3, 'garlic' => 8, 'ginger' => 8, 'cashew' => 15],
        'Lamb Rogan Josh' => ['lamb_shoulder' => 220, 'onion' => 80, 'yoghurt' => 40, 'ginger' => 10, 'kashmiri' => 4, 'ghee' => 20],
        'Goan Prawn Curry' => ['prawns' => 180, 'coconut' => 120, 'tomato' => 60, 'kashmiri' => 4, 'tamarind' => 8, 'onion' => 60],
        'Chana Masala' => ['chickpeas' => 70, 'onion' => 80, 'tomato' => 100, 'ginger' => 8, 'garam' => 2],
        'Chicken Chettinad' => ['chicken' => 200, 'coconut' => 60, 'onion' => 80, 'curry_leaf' => 2, 'kashmiri' => 3, 'cumin' => 1, 'garam' => 2],
        'Palak Paneer' => ['paneer' => 130, 'spinach' => 250, 'cream' => 30, 'garlic' => 8, 'onion' => 40, 'fenugreek' => 1],
        'Hyderabadi Lamb Biryani' => ['basmati' => 180, 'lamb_shoulder' => 200, 'yoghurt' => 60, 'onion' => 80, 'flour' => 30, 'ghee' => 25, 'mint' => 8, 'garam' => 3],
        'Jackfruit Biryani' => ['basmati' => 170, 'jackfruit' => 200, 'onion' => 80, 'mint' => 8, 'flour' => 40, 'garam' => 3, 'oil' => 30],
        'Steamed Basmati' => ['basmati' => 120],
        'Jeera Rice' => ['basmati' => 120, 'cumin' => 3, 'ghee' => 10],
        'Lemon Rice' => ['basmati' => 120, 'mustard_seed' => 2, 'curry_leaf' => 1, 'turmeric' => 1, 'limes' => 1],
        'Butter Naan' => ['flour' => 130, 'yoghurt' => 20, 'butter' => 15, 'milk' => 30],
        'Garlic and Coriander Naan' => ['flour' => 130, 'yoghurt' => 20, 'garlic' => 10, 'coriander' => 4, 'butter' => 10],
        'Cheese and Chilli Kulcha' => ['flour' => 130, 'cheddar' => 50, 'green_chilli' => 5, 'onion' => 20],
        'Tandoori Roti' => ['atta' => 90],
        'Laccha Paratha' => ['atta' => 110, 'ghee' => 20],
        'Kachumber Salad' => ['cucumber' => 80, 'tomato' => 60, 'onion' => 40, 'chaat' => 2, 'limes' => 1],
        'Bombay Potatoes' => ['potato' => 220, 'mustard_seed' => 2, 'curry_leaf' => 1, 'turmeric' => 1, 'oil' => 20],
        'Cucumber Raita' => ['yoghurt' => 150, 'cucumber' => 50, 'cumin' => 1, 'mint' => 3],
        'Poppadoms and Pickles' => ['poppadom' => 2, 'tamarind' => 20, 'mint' => 5, 'limes' => 1],
        'Gulab Jamun' => ['cream' => 60, 'flour' => 30, 'sugar' => 40, 'cardamom' => 1, 'oil' => 30, 'milk' => 40],
        'Pistachio Kulfi' => ['milk' => 200, 'sugar' => 30, 'pistachio' => 15, 'cardamom' => 1, 'cream' => 40],
        'Mango Shrikhand' => ['yoghurt' => 180, 'mango' => 80, 'sugar' => 25, 'cardamom' => 1, 'flour' => 20, 'butter' => 15],
        'Mango Lassi' => ['mango' => 100, 'yoghurt' => 120, 'milk' => 60, 'cardamom' => 1],
        'Masala Chai' => ['tea' => 5, 'milk' => 150, 'sugar' => 10, 'ginger' => 4, 'cardamom' => 1],
        'Fresh Lime Soda' => ['limes' => 2, 'soda' => 250, 'sugar' => 20],
        'Indian Pale Ale' => ['ipa' => 1],
    ];

    /**
     * Ingredient, grams/ml/pieces range, reason.
     *
     * @var list<array{string, int, int, string}>
     */
    private const WASTE = [
        ['flour', 800, 1500, 'Over-proofed dough'],
        ['flour', 600, 1000, 'Over-proofed dough, naan batch'],
        ['yoghurt', 500, 1000, 'Split in the walk-in, past use-by'],
        ['cream', 400, 800, 'Curdled during service'],
        ['chicken', 400, 900, 'Dropped tray'],
        ['chicken', 600, 1200, 'Marinated chicken past use-by'],
        ['lamb_mince', 300, 600, 'Dropped tray on the pass'],
        ['prawns', 200, 400, 'Delivery arrived warm, rejected'],
        ['hake', 200, 400, 'Off smell on opening'],
        ['coriander', 150, 300, 'Wilted'],
        ['mint', 50, 150, 'Wilted'],
        ['spinach', 300, 600, 'Slimy, past best'],
        ['tomato', 500, 1000, 'Bruised in the crate'],
        ['potato', 500, 1000, 'Gone green'],
        ['onion', 500, 1200, 'Soft, binned at prep'],
        ['basmati', 500, 1000, 'Overcooked batch'],
        ['urad', 400, 800, 'Burnt base on the daal pot'],
        ['paneer', 300, 500, 'Dropped on the floor'],
        ['ipa', 2, 3, 'Broken bottles in the crate'],
        ['milk', 1000, 2000, 'Spilled'],
        ['oil', 1000, 2000, 'Fryer oil changed'],
        ['butter', 200, 300, 'Left out overnight'],
        ['broccoli', 300, 500, 'Past best'],
        ['ghee', 150, 250, 'Burnt in the tadka'],
        ['gram_flour', 400, 600, 'Damp bag'],
        ['cucumber', 300, 600, 'Soft'],
    ];

    /** Ingredients that are running short as of now. */
    private const LOW = ['kashmiri', 'fenugreek', 'cardamom', 'lamb_chops', 'mint', 'tea'];

    /**
     * Delivery weekdays (ISO) per supplier index; the bakery round is the last entry.
     *
     * @var array<int|string, list<int>>
     */
    private const SCHEDULE = [0 => [4], 1 => [2, 5], 2 => [1, 3, 5], 3 => [2, 4, 6], 4 => [2, 5], 5 => [3], 'bakery' => [2, 5]];

    public function run(): void
    {
        DB::transaction(function () {
            $suppliers = $this->suppliers();
            $ingredients = $this->ingredients($suppliers);
            $this->recipes($ingredients);
            $this->movements($ingredients);
            $this->purchaseOrders($suppliers, $ingredients);
        });
    }

    /**
     * @return list<Supplier>
     */
    private function suppliers(): array
    {
        return array_map(fn (array $supplier) => Supplier::create([
            'name' => $supplier[0],
            'contact_name' => $supplier[1],
            'email' => $supplier[2],
            'phone' => $supplier[3],
            'lead_days' => (int) $supplier[4],
        ]), self::SUPPLIERS);
    }

    /**
     * @param  list<Supplier>  $suppliers
     * @return array<string, Ingredient>
     */
    private function ingredients(array $suppliers): array
    {
        $created = [];

        foreach (self::INGREDIENTS as $key => [$name, $unit, $supplier, $cost, $par]) {
            $created[$key] = Ingredient::create([
                'supplier_id' => $supplier !== null ? $suppliers[$supplier]->id : null,
                'name' => $name,
                'unit' => $unit,
                'stock' => 0,
                'low_threshold' => (int) (round($par * 0.2 / 10) * 10) ?: 1,
                'par_level' => $par,
                'cost_per_thousand' => $cost,
            ]);
        }

        return $created;
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     */
    private function recipes(array $ingredients): void
    {
        $rows = [];

        foreach (MenuItem::all() as $item) {
            $recipe = self::RECIPES[$item->name] ?? throw new \LogicException("No recipe for {$item->name}.");

            foreach ($recipe as $key => $quantity) {
                $rows[] = ['menu_item_id' => $item->id, 'ingredient_id' => $ingredients[$key]->id, 'quantity' => $quantity];
            }
        }

        DB::table('recipe_lines')->insert($rows);
    }

    /**
     * @param  array<string, Ingredient>  $ingredients
     */
    private function movements(array $ingredients): void
    {
        $now = Trading::now();
        $kitchen = User::where('role', Role::Kitchen)->pluck('id')->all();
        $manager = User::where('role', Role::Manager)->value('id');
        $byId = [];
        $keyById = [];

        foreach ($ingredients as $key => $ingredient) {
            $keyById[$ingredient->id] = $key;
            $byId[$ingredient->id] = [];
        }

        $recipes = DB::table('recipe_lines')->get()->groupBy('menu_item_id');
        $usage = [];
        $acceptedAt = [];

        $lines = DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->whereNotNull('orders.accepted_at')
            ->whereNotIn('orders.status', ['rejected', 'cancelled'])
            ->get(['orders.id as order_id', 'orders.accepted_at', 'order_lines.menu_item_id', 'order_lines.variant_name', 'order_lines.quantity']);

        foreach ($lines as $line) {
            $multiplier = match ($line->variant_name) {
                'Large' => 1.5,
                'Full' => 1.6,
                default => 1.0,
            };
            $acceptedAt[$line->order_id] = CarbonImmutable::parse($line->accepted_at, config('app.timezone'));

            foreach ($recipes->get($line->menu_item_id, collect()) as $recipeLine) {
                $usage[$line->order_id][$recipeLine->ingredient_id] = ($usage[$line->order_id][$recipeLine->ingredient_id] ?? 0)
                    + (int) round($recipeLine->quantity * $line->quantity * $multiplier);
            }
        }

        foreach ($usage as $orderId => $perIngredient) {
            foreach ($perIngredient as $ingredientId => $quantity) {
                $byId[$ingredientId][] = ['t' => $acceptedAt[$orderId], 'quantity' => -$quantity, 'type' => StockMovementType::Sale, 'reason' => null, 'order_id' => (int) $orderId, 'user_id' => null];
            }
        }

        foreach (self::WASTE as [$key, $min, $max, $reason]) {
            $day = $now->subDays(mt_rand(1, 45))->setTime(mt_rand(10, 21), mt_rand(0, 59));
            $byId[$ingredients[$key]->id][] = ['t' => $day, 'quantity' => -mt_rand($min, $max), 'type' => StockMovementType::Waste, 'reason' => $reason, 'order_id' => null, 'user_id' => Dice::pick($kitchen)];
        }

        // The dairy order that arrived eight days ago is part of the history.
        $received = $now->subDays(8)->setTime(7, 50);

        foreach (['yoghurt' => 10000, 'cream' => 6000, 'paneer' => 5000, 'butter' => 3000] as $key => $quantity) {
            $byId[$ingredients[$key]->id][] = ['t' => $received, 'quantity' => $quantity, 'type' => StockMovementType::In, 'reason' => 'Delivery against PO-2026-0031', 'order_id' => null, 'user_id' => $manager];
        }

        $start = $now->subDays(61)->setTime(6, 0);
        $rows = [];

        foreach ($ingredients as $key => $ingredient) {
            $events = $byId[$ingredient->id];
            $schedule = self::SCHEDULE[self::INGREDIENTS[$key][2] ?? 'bakery'];

            for ($day = $start->startOfDay(); $day <= $now; $day = $day->addDay()) {
                if (in_array($day->dayOfWeekIso, $schedule, true)) {
                    $events[] = ['t' => $day->setTime(7, 30), 'check' => true];
                }
            }

            usort($events, fn (array $a, array $b) => $a['t']->getTimestamp() <=> $b['t']->getTimestamp());

            $opening = (int) round($ingredient->par_level * 0.55);
            $stock = $opening;
            $isLow = in_array($key, self::LOW, true);
            $rows[] = $this->row($ingredient->id, $start, StockMovementType::Adjust, $opening, 'Opening stock count', null, $manager);

            foreach ($events as $event) {
                if (isset($event['check'])) {
                    if ($stock < $ingredient->par_level * 0.6 && $event['t'] <= $now && ! ($isLow && $event['t'] > $now->subDays(4))) {
                        $rows[] = $this->row($ingredient->id, $event['t'], StockMovementType::In, $ingredient->par_level - $stock, 'Scheduled delivery', null, Dice::pick($kitchen));
                        $stock = $ingredient->par_level;
                    }

                    continue;
                }

                $rows[] = $this->row($ingredient->id, $event['t'], $event['type'], $event['quantity'], $event['reason'], $event['order_id'], $event['user_id']);
                $stock += $event['quantity'];
            }

            if ($isLow) {
                $target = (int) max(1, round($ingredient->low_threshold * mt_rand(35, 85) / 100));
                $rows[] = $this->row($ingredient->id, $now->subMinute(), StockMovementType::Adjust, $target - $stock, 'Stocktake: shelf count', null, $manager);
                $stock = $target;
            } elseif ($stock < $ingredient->low_threshold) {
                $rows[] = $this->row($ingredient->id, $now->subHours(2), StockMovementType::In, $ingredient->par_level - $stock, 'Top-up delivery', null, $manager);
                $stock = $ingredient->par_level;
            }

            $ingredient->forceFill(['stock' => $stock])->save();
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('stock_movements')->insert($chunk);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $ingredientId, CarbonImmutable $at, StockMovementType $type, int $quantity, ?string $reason, ?int $orderId, ?int $userId): array
    {
        return [
            'ingredient_id' => $ingredientId,
            'type' => $type->value,
            'quantity' => $quantity,
            'reason' => $reason,
            'order_id' => $orderId,
            'user_id' => $userId,
            'created_at' => Trading::stamp($at),
        ];
    }

    /**
     * @param  list<Supplier>  $suppliers
     * @param  array<string, Ingredient>  $ingredients
     */
    private function purchaseOrders(array $suppliers, array $ingredients): void
    {
        $now = Trading::now();
        $manager = User::where('role', Role::Manager)->value('id');

        $short = fn (string $key): int => (int) (ceil(($ingredients[$key]->par_level - $ingredients[$key]->stock) / 100) * 100);

        $orders = [
            ['PO-2026-0033', 0, PurchaseOrderStatus::Draft, null, null, ['kashmiri' => $short('kashmiri'), 'fenugreek' => $short('fenugreek'), 'cardamom' => $short('cardamom'), 'tea' => $short('tea')]],
            ['PO-2026-0032', 1, PurchaseOrderStatus::Sent, $now->subDay()->setTime(16, 20), null, ['chicken' => 15000, 'lamb_shoulder' => 10000, 'lamb_chops' => $short('lamb_chops')]],
            ['PO-2026-0031', 2, PurchaseOrderStatus::Received, $now->subDays(9)->setTime(15, 30), $now->subDays(8)->setTime(7, 50), ['yoghurt' => 10000, 'cream' => 6000, 'paneer' => 5000, 'butter' => 3000]],
        ];

        foreach ($orders as [$reference, $supplier, $status, $sentAt, $receivedAt, $lines]) {
            $total = 0;
            $po = PurchaseOrder::create([
                'reference' => $reference,
                'supplier_id' => $suppliers[$supplier]->id,
                'status' => $status,
                'total' => 0,
                'sent_at' => $sentAt,
                'received_at' => $receivedAt,
                'created_by' => $manager,
            ]);

            foreach ($lines as $key => $quantity) {
                $unitCost = $ingredients[$key]->cost_per_thousand;
                $total += intdiv($quantity * $unitCost, 1000);
                $po->lines()->create(['ingredient_id' => $ingredients[$key]->id, 'quantity' => $quantity, 'unit_cost' => $unitCost]);
            }

            $po->forceFill(['total' => $total])->save();
        }
    }
}

<?php

use App\Actions\Inventory\PurchaseOrders;
use App\Actions\Inventory\RecipeCosting;
use App\Actions\Inventory\StockLedger;
use App\Enums\PurchaseOrderStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->travelTo('2026-10-28 18:00:00'));

function invUser(Role $role): User
{
    return User::factory()->role($role)->create();
}

function invIngredient(array $attributes = []): Ingredient
{
    return Ingredient::factory()->create($attributes);
}

function invMovement(array $attributes): StockMovement
{
    $movement = (new StockMovement)->forceFill($attributes);
    $movement->save();

    return $movement;
}

function invOrder(Supplier $supplier, PurchaseOrderStatus $status, array $lines = []): PurchaseOrder
{
    $order = PurchaseOrder::create([
        'reference' => 'PO-2026-'.str_pad((string) (PurchaseOrder::count() + 1), 4, '0', STR_PAD_LEFT),
        'supplier_id' => $supplier->id,
        'status' => $status,
        'total' => 0,
    ]);

    foreach ($lines as $ingredient => $quantity) {
        $order->lines()->create(['ingredient_id' => $ingredient, 'quantity' => $quantity, 'unit_cost' => Ingredient::find($ingredient)->cost_per_thousand]);
    }

    return $order;
}

it('lets each role into the stock pages it may see', function (Role $role, int $stock, int $costs) {
    $order = invOrder(Supplier::factory()->create(), PurchaseOrderStatus::Draft);

    foreach (['', '/waste', '/movements'] as $path) {
        $this->actingAs(invUser($role))->get("/staff/inventory{$path}")->assertStatus($stock);
    }

    foreach (['/recipes', '/suppliers', '/purchase-orders', "/purchase-orders/{$order->id}", "/purchase-orders/{$order->id}/pdf"] as $path) {
        $this->actingAs(invUser($role))->get("/staff/inventory{$path}")->assertStatus($costs);
    }
})->with([
    'kitchen' => [Role::Kitchen, 200, 403],
    'manager' => [Role::Manager, 200, 200],
    'owner' => [Role::Owner, 200, 200],
    'waiter' => [Role::Waiter, 403, 403],
    'cashier' => [Role::Cashier, 403, 403],
    'rider' => [Role::Rider, 403, 403],
]);

it('keeps front-of-house out of every write', function (Role $role) {
    $ingredient = invIngredient();
    $user = invUser($role);

    $this->actingAs($user)->post("/staff/inventory/ingredients/{$ingredient->id}/stock-in", ['quantity' => 100])->assertForbidden();
    $this->actingAs($user)->post('/staff/inventory/waste', ['ingredient_id' => $ingredient->id, 'quantity' => 10, 'reason' => 'dropped'])->assertForbidden();
    expect($ingredient->fresh()->stock)->toBe(8000)->and(StockMovement::count())->toBe(0);
})->with([Role::Waiter, Role::Cashier, Role::Rider, Role::Customer]);

it('keeps kitchen out of ingredient, recipe, supplier and purchase order editing', function () {
    $kitchen = invUser(Role::Kitchen);
    $ingredient = invIngredient();
    $dish = MenuItem::factory()->create();
    $supplier = $ingredient->supplier;
    $draft = invOrder($supplier, PurchaseOrderStatus::Draft, [$ingredient->id => 500]);
    $payload = ['name' => 'Saffron', 'unit' => 'g', 'low_threshold' => 5, 'par_level' => 50, 'cost_per_thousand' => 90000, 'supplier_id' => null];

    $this->actingAs($kitchen);
    $this->post('/staff/inventory/ingredients', $payload)->assertForbidden();
    $this->patch("/staff/inventory/ingredients/{$ingredient->id}", $payload)->assertForbidden();
    $this->put("/staff/inventory/recipes/{$dish->id}", ['lines' => []])->assertForbidden();
    $this->post('/staff/inventory/suppliers', ['name' => 'X', 'lead_days' => 1])->assertForbidden();
    $this->patch("/staff/inventory/suppliers/{$supplier->id}", ['name' => 'X', 'lead_days' => 1])->assertForbidden();
    $this->post('/staff/inventory/purchase-orders', ['supplier_id' => $supplier->id])->assertForbidden();
    $this->put("/staff/inventory/purchase-orders/{$draft->id}/lines", ['lines' => []])->assertForbidden();
    $this->post("/staff/inventory/purchase-orders/{$draft->id}/send")->assertForbidden();
    $this->post("/staff/inventory/purchase-orders/{$draft->id}/cancel")->assertForbidden();
    $this->post("/staff/inventory/purchase-orders/{$draft->id}/receive")->assertForbidden();

    expect($draft->fresh()->status)->toBe(PurchaseOrderStatus::Draft);
});

it('tells the page whether the viewer can manage', function (Role $role, bool $manage) {
    $this->actingAs(invUser($role))->get('/staff/inventory')->assertInertia(fn ($page) => $page->where('abilities.manage', $manage));
})->with([[Role::Kitchen, false], [Role::Manager, true], [Role::Owner, true]]);

it('lists ingredients with low ones first and honours filters', function () {
    $supplier = Supplier::factory()->create();
    invIngredient(['name' => 'Ghee', 'supplier_id' => $supplier->id, 'stock' => 9000]);
    invIngredient(['name' => 'Cumin', 'supplier_id' => $supplier->id, 'stock' => 1500, 'low_threshold' => 2000]);
    invIngredient(['name' => 'Turmeric', 'stock' => 9000]);

    $get = fn (string $query = '') => $this->actingAs(invUser(Role::Manager))->get('/staff/inventory'.$query);

    $get()->assertInertia(fn ($page) => $page->missing('ingredients')->loadDeferredProps(fn ($page) => $page
        ->has('ingredients', 3)
        ->where('ingredients.0.name', 'Cumin')
        ->where('ingredients.0.is_low', true)
        ->where('ingredients.0.value', 150 * 0 + (int) round(1500 * Ingredient::where('name', 'Cumin')->value('cost_per_thousand') / 1000))));

    $get('?low=1')->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($page) => $page->has('ingredients', 1)));
    $get('?supplier='.$supplier->id)->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($page) => $page->has('ingredients', 2)));
    $get('?q=turm')->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($page) => $page->has('ingredients', 1)->where('ingredients.0.name', 'Turmeric')));
});

it('flags an ingredient exactly at its low mark as low', function () {
    invIngredient(['stock' => 2000, 'low_threshold' => 2000]);

    $this->actingAs(invUser(Role::Manager))->get('/staff/inventory')->assertInertia(fn ($page) => $page
        ->where('summary.low', 1)
        ->where('lowCount', 1));
});

it('adds stock in with a movement and a log row', function () {
    $kitchen = invUser(Role::Kitchen);
    $ingredient = invIngredient(['stock' => 1000]);

    $this->actingAs($kitchen)->post("/staff/inventory/ingredients/{$ingredient->id}/stock-in", ['quantity' => 5000, 'reason' => 'Delivery note 88'])->assertRedirect();

    $movement = StockMovement::sole();
    expect($ingredient->fresh()->stock)->toBe(6000)
        ->and($movement->type)->toBe(StockMovementType::In)
        ->and($movement->quantity)->toBe(5000)
        ->and($movement->reason)->toBe('Delivery note 88')
        ->and($movement->user_id)->toBe($kitchen->id)
        ->and(ActivityLog::where('event', 'inventory.stock_in')->count())->toBe(1);
});

it('validates stock in', function () {
    $ingredient = invIngredient();

    $this->actingAs(invUser(Role::Kitchen))->post("/staff/inventory/ingredients/{$ingredient->id}/stock-in", ['quantity' => 0])->assertSessionHasErrors('quantity');
    expect(StockMovement::count())->toBe(0);
});

it('corrects stock to a counted level with a signed movement', function () {
    $ingredient = invIngredient(['stock' => 4000]);
    $kitchen = invUser(Role::Kitchen);

    $this->actingAs($kitchen)->post("/staff/inventory/ingredients/{$ingredient->id}/adjust", ['counted' => 3200, 'reason' => 'Weekly count'])->assertRedirect();
    $this->post("/staff/inventory/ingredients/{$ingredient->id}/adjust", ['counted' => 3500, 'reason' => 'Found another tub'])->assertRedirect();

    expect($ingredient->fresh()->stock)->toBe(3500)
        ->and(StockMovement::orderBy('id')->pluck('quantity')->all())->toBe([-800, 300])
        ->and(StockMovement::where('type', StockMovementType::Adjust)->count())->toBe(2)
        ->and(ActivityLog::where('event', 'inventory.adjust')->count())->toBe(2);
});

it('needs a reason and a real change for a count', function () {
    $ingredient = invIngredient(['stock' => 4000]);
    $this->actingAs(invUser(Role::Kitchen));

    $this->post("/staff/inventory/ingredients/{$ingredient->id}/adjust", ['counted' => 3000])->assertSessionHasErrors('reason');
    $this->post("/staff/inventory/ingredients/{$ingredient->id}/adjust", ['counted' => 4000, 'reason' => 'Same'])->assertSessionHasErrors('counted');
    expect(StockMovement::count())->toBe(0)->and($ingredient->fresh()->stock)->toBe(4000);
});

it('logs waste against stock with the reason and note', function () {
    $ingredient = invIngredient(['stock' => 5000, 'cost_per_thousand' => 1000]);
    $kitchen = invUser(Role::Kitchen);

    $this->actingAs($kitchen)->post('/staff/inventory/waste', ['ingredient_id' => $ingredient->id, 'quantity' => 800, 'reason' => 'over_proofed', 'note' => 'naan batch'])->assertRedirect();

    $movement = StockMovement::sole();
    expect($ingredient->fresh()->stock)->toBe(4200)
        ->and($movement->type)->toBe(StockMovementType::Waste)
        ->and($movement->quantity)->toBe(-800)
        ->and($movement->reason)->toBe('Over-proofed: naan batch');
});

it('refuses waste larger than the stock and "other" without a note', function () {
    $ingredient = invIngredient(['stock' => 500]);
    $this->actingAs(invUser(Role::Kitchen));

    $this->post('/staff/inventory/waste', ['ingredient_id' => $ingredient->id, 'quantity' => 900, 'reason' => 'spoiled'])->assertSessionHasErrors('quantity');
    $this->post('/staff/inventory/waste', ['ingredient_id' => $ingredient->id, 'quantity' => 100, 'reason' => 'other'])->assertSessionHasErrors('note');
    $this->post('/staff/inventory/waste', ['ingredient_id' => $ingredient->id, 'quantity' => 100, 'reason' => 'nonsense'])->assertSessionHasErrors('reason');

    expect($ingredient->fresh()->stock)->toBe(500)->and(StockMovement::count())->toBe(0);
});

it('writes the movement and the stock level together or not at all', function () {
    $ingredient = invIngredient(['stock' => 1000]);
    ActivityLog::creating(fn () => throw new RuntimeException('log unavailable'));

    expect(fn () => $this->withoutExceptionHandling()->actingAs(invUser(Role::Kitchen))
        ->post("/staff/inventory/ingredients/{$ingredient->id}/stock-in", ['quantity' => 500]))->toThrow(RuntimeException::class);

    expect($ingredient->fresh()->stock)->toBe(1000)->and(StockMovement::count())->toBe(0);
});

it('summarises this weeks waste and its cost', function () {
    $ingredient = invIngredient(['stock' => 9000, 'cost_per_thousand' => 2000, 'name' => 'Lamb']);
    $kitchen = invUser(Role::Kitchen);
    invMovement(['ingredient_id' => $ingredient->id, 'type' => StockMovementType::Waste, 'quantity' => -1000, 'reason' => 'Old', 'user_id' => $kitchen->id, 'created_at' => '2026-10-15 12:00:00']);

    $this->actingAs($kitchen)->post('/staff/inventory/waste', ['ingredient_id' => $ingredient->id, 'quantity' => 1500, 'reason' => 'dropped']);

    $this->actingAs(invUser(Role::Manager))->get('/staff/inventory/waste')->assertInertia(fn ($page) => $page
        ->where('summary.cost', 3000)
        ->where('summary.entries', 1)
        ->where('summary.top.0.name', 'Lamb')
        ->loadDeferredProps(fn ($page) => $page->has('entries.data', 2)));
});

it('filters the movement history', function () {
    $a = invIngredient();
    $b = invIngredient();
    invMovement(['ingredient_id' => $a->id, 'type' => StockMovementType::In, 'quantity' => 100, 'created_at' => '2026-10-01 09:00:00']);
    invMovement(['ingredient_id' => $a->id, 'type' => StockMovementType::Waste, 'quantity' => -10, 'created_at' => '2026-10-20 09:00:00']);
    invMovement(['ingredient_id' => $b->id, 'type' => StockMovementType::Waste, 'quantity' => -10, 'created_at' => '2026-10-25 09:00:00']);

    $this->actingAs(invUser(Role::Kitchen));
    $expectTotal = fn (string $query, int $total) => $this->get('/staff/inventory/movements?'.$query)
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($page) => $page->where('movements.total', $total)->etc()));

    $expectTotal('', 3);
    $expectTotal('type=waste', 2);
    $expectTotal('ingredient='.$a->id, 2);
    $expectTotal('from=2026-10-15&to=2026-10-22', 1);
});

it('lets managers add and edit ingredients and checks the levels', function () {
    $supplier = Supplier::factory()->create();
    $manager = invUser(Role::Manager);
    $payload = ['name' => 'Saffron', 'unit' => 'g', 'low_threshold' => 5, 'par_level' => 50, 'cost_per_thousand' => 900000, 'supplier_id' => $supplier->id];

    $this->actingAs($manager)->post('/staff/inventory/ingredients', $payload)->assertRedirect();
    $saffron = Ingredient::where('name', 'Saffron')->sole();
    expect($saffron->stock)->toBe(0)->and($saffron->supplier_id)->toBe($supplier->id);

    $this->patch("/staff/inventory/ingredients/{$saffron->id}", [...$payload, 'par_level' => 80])->assertRedirect();
    expect($saffron->fresh()->par_level)->toBe(80);

    $this->patch("/staff/inventory/ingredients/{$saffron->id}", [...$payload, 'par_level' => 2])->assertSessionHasErrors('par_level');
    $this->post('/staff/inventory/ingredients', [...$payload, 'unit' => 'lb'])->assertSessionHasErrors('unit');
});

it('lets managers add and edit suppliers', function () {
    $this->actingAs(invUser(Role::Owner));

    $this->post('/staff/inventory/suppliers', ['name' => 'Fresh Fish', 'contact_name' => 'Dean', 'email' => 'dean@example.test', 'phone' => '020 1', 'lead_days' => 2])->assertRedirect();
    $supplier = Supplier::where('name', 'Fresh Fish')->sole();

    $this->patch("/staff/inventory/suppliers/{$supplier->id}", ['name' => 'Fresh Fish Co', 'lead_days' => 1])->assertRedirect();
    expect($supplier->fresh()->name)->toBe('Fresh Fish Co')->and($supplier->fresh()->lead_days)->toBe(1);

    $this->post('/staff/inventory/suppliers', ['name' => '', 'email' => 'nope', 'lead_days' => 99])->assertSessionHasErrors(['name', 'email', 'lead_days']);
});

it('works out recipe cost and gross margin without VAT', function () {
    // 200 g at 650 and 100 g at 1100 per kg: 130p + 110p = 240p a portion.
    $chicken = invIngredient(['cost_per_thousand' => 650]);
    $yoghurt = invIngredient(['cost_per_thousand' => 1100]);
    $dish = MenuItem::factory()->create(['name' => 'Tikka', 'price' => 1200, 'vat_rate' => 2000]);
    $dish->recipeLines()->create(['ingredient_id' => $chicken->id, 'quantity' => 200]);
    $dish->recipeLines()->create(['ingredient_id' => $yoghurt->id, 'quantity' => 100]);

    $this->actingAs(invUser(Role::Manager))->get('/staff/inventory/recipes')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page
            ->where('dishes.0.cost', 240)
            ->where('dishes.0.net_price', 1000)
            ->where('dishes.0.margin', 76)
            ->where('dishes.0.flagged', false)));

    expect(RecipeCosting::netPrice(1200, 2000))->toBe(1000)
        ->and(RecipeCosting::netPrice(1250, 0))->toBe(1250)
        ->and(RecipeCosting::margin(1000, 240))->toBe(76.0)
        ->and(RecipeCosting::margin(0, 0))->toBeNull();
});

it('flags dishes under the margin target and leaves unrecipe-d dishes alone', function () {
    $saffron = invIngredient(['cost_per_thousand' => 5000]);
    $costly = MenuItem::factory()->create(['name' => 'Costly', 'price' => 1200, 'vat_rate' => 2000]);
    $costly->recipeLines()->create(['ingredient_id' => $saffron->id, 'quantity' => 100]); // 500p on 1000p net: 50%
    $bare = MenuItem::factory()->create(['name' => 'Bare', 'price' => 600]);

    $this->actingAs(invUser(Role::Manager))->get('/staff/inventory/recipes')->assertInertia(fn ($page) => $page
        ->where('target', 65)
        ->loadDeferredProps(fn ($page) => $page
            ->where('dishes.0.name', 'Costly')
            ->where('dishes.0.margin', 50)
            ->where('dishes.0.flagged', true)
            ->where('dishes.1.name', 'Bare')
            ->where('dishes.1.cost', null)
            ->where('dishes.1.flagged', false)));

    $this->get('/staff/inventory/recipes?flagged=1')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page->has('dishes', 1)));
});

it('replaces a recipe and refuses a repeated ingredient', function () {
    $a = invIngredient();
    $b = invIngredient();
    $dish = MenuItem::factory()->create();
    $dish->recipeLines()->create(['ingredient_id' => $a->id, 'quantity' => 50]);

    $this->actingAs(invUser(Role::Manager));
    $this->put("/staff/inventory/recipes/{$dish->id}", ['lines' => [['ingredient_id' => $b->id, 'quantity' => 120]]])->assertRedirect();
    expect($dish->recipeLines()->pluck('quantity', 'ingredient_id')->all())->toBe([$b->id => 120]);

    $this->put("/staff/inventory/recipes/{$dish->id}", ['lines' => [['ingredient_id' => $b->id, 'quantity' => 1], ['ingredient_id' => $b->id, 'quantity' => 2]]])->assertSessionHasErrors('lines.0.ingredient_id');
    expect($dish->recipeLines()->count())->toBe(1);
});

it('suggests purchase order lines that bring each low ingredient up to par', function () {
    $supplier = Supplier::factory()->create();
    $low = invIngredient(['supplier_id' => $supplier->id, 'stock' => 1500, 'low_threshold' => 2000, 'par_level' => 10000, 'cost_per_thousand' => 500]);
    $atMark = invIngredient(['supplier_id' => $supplier->id, 'stock' => 2000, 'low_threshold' => 2000, 'par_level' => 6000, 'cost_per_thousand' => 1000]);
    $fine = invIngredient(['supplier_id' => $supplier->id, 'stock' => 9000]);
    $other = invIngredient(['stock' => 10, 'low_threshold' => 2000]);

    $this->actingAs(invUser(Role::Manager))->post('/staff/inventory/purchase-orders', ['supplier_id' => $supplier->id])->assertRedirect();

    $order = PurchaseOrder::sole();
    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->reference)->toMatch('/^PO-[A-HJ-NP-Z2-9]{8}$/')
        ->and($order->supplier_id)->toBe($supplier->id)
        ->and($order->lines->pluck('quantity', 'ingredient_id')->all())->toEqual([$low->id => 8500, $atMark->id => 4000])
        ->and($order->lines->pluck('ingredient_id')->all())->not->toContain($fine->id, $other->id)
        // 8500 g at 500 + 4000 g at 1000 per kg = 4250p + 4000p.
        ->and($order->total)->toBe(8250);

    // Receiving the suggested quantities lands exactly on par.
    expect($low->stock + 8500)->toBe($low->par_level)->and($atMark->stock + 4000)->toBe($atMark->par_level);
});

it('gives every purchase order its own short reference', function () {
    $supplier = Supplier::factory()->create();
    $this->actingAs(invUser(Role::Manager));

    $this->post('/staff/inventory/purchase-orders', ['supplier_id' => $supplier->id]);
    $this->post('/staff/inventory/purchase-orders', ['supplier_id' => $supplier->id]);

    $references = PurchaseOrder::pluck('reference');
    expect($references)->toHaveCount(2)->and($references->unique())->toHaveCount(2);
});

it('edits the lines of a draft and recalculates the total', function () {
    $supplier = Supplier::factory()->create();
    $a = invIngredient(['supplier_id' => $supplier->id, 'cost_per_thousand' => 1000]);
    $b = invIngredient(['supplier_id' => $supplier->id, 'cost_per_thousand' => 200]);
    $order = invOrder($supplier, PurchaseOrderStatus::Draft, [$a->id => 1000]);

    $this->actingAs(invUser(Role::Manager))->put("/staff/inventory/purchase-orders/{$order->id}/lines", ['lines' => [
        ['ingredient_id' => $a->id, 'quantity' => 3000],
        ['ingredient_id' => $b->id, 'quantity' => 5000],
    ]])->assertRedirect();

    expect($order->fresh()->total)->toBe(4000)->and($order->lines()->count())->toBe(2);

    $sent = invOrder($supplier, PurchaseOrderStatus::Sent, [$a->id => 1000]);
    $this->put("/staff/inventory/purchase-orders/{$sent->id}/lines", ['lines' => [['ingredient_id' => $a->id, 'quantity' => 1]]])->assertSessionHasErrors('status');
    expect($sent->lines()->first()->quantity)->toBe(1000);
});

it('marks a draft sent and records when', function () {
    $supplier = Supplier::factory()->create();
    $ingredient = invIngredient(['supplier_id' => $supplier->id]);
    $order = invOrder($supplier, PurchaseOrderStatus::Draft, [$ingredient->id => 1000]);
    $empty = invOrder($supplier, PurchaseOrderStatus::Draft);

    $this->actingAs(invUser(Role::Manager));
    $this->post("/staff/inventory/purchase-orders/{$empty->id}/send")->assertSessionHasErrors('status');
    $this->post("/staff/inventory/purchase-orders/{$order->id}/send")->assertRedirect();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Sent)
        ->and($order->fresh()->sent_at?->toDateTimeString())->toBe('2026-10-28 18:00:00')
        ->and($empty->fresh()->status)->toBe(PurchaseOrderStatus::Draft);
});

it('adds every line to stock when an order is received', function () {
    $supplier = Supplier::factory()->create();
    $a = invIngredient(['supplier_id' => $supplier->id, 'stock' => 1000]);
    $b = invIngredient(['supplier_id' => $supplier->id, 'stock' => 0]);
    $order = invOrder($supplier, PurchaseOrderStatus::Sent, [$a->id => 4000, $b->id => 250]);
    $manager = invUser(Role::Manager);

    $this->actingAs($manager)->post("/staff/inventory/purchase-orders/{$order->id}/receive")->assertRedirect();

    expect($a->fresh()->stock)->toBe(5000)
        ->and($b->fresh()->stock)->toBe(250)
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->fresh()->received_at)->not->toBeNull()
        ->and(StockMovement::where('type', StockMovementType::In)->count())->toBe(2)
        ->and(StockMovement::first()->reason)->toBe("Delivery against {$order->reference}");

    // A second press must not double the stock.
    $this->post("/staff/inventory/purchase-orders/{$order->id}/receive")->assertSessionHasErrors('status');
    expect($a->fresh()->stock)->toBe(5000)->and(StockMovement::count())->toBe(2);
});

it('will not receive a draft or a cancelled order', function () {
    $supplier = Supplier::factory()->create();
    $ingredient = invIngredient(['supplier_id' => $supplier->id, 'stock' => 100]);
    $draft = invOrder($supplier, PurchaseOrderStatus::Draft, [$ingredient->id => 500]);
    $cancelled = invOrder($supplier, PurchaseOrderStatus::Cancelled, [$ingredient->id => 500]);

    $this->actingAs(invUser(Role::Manager));
    $this->post("/staff/inventory/purchase-orders/{$draft->id}/receive")->assertSessionHasErrors('status');
    $this->post("/staff/inventory/purchase-orders/{$cancelled->id}/receive")->assertSessionHasErrors('status');

    expect($ingredient->fresh()->stock)->toBe(100)->and(StockMovement::count())->toBe(0);
});

it('rolls back the whole delivery if one line fails', function () {
    $supplier = Supplier::factory()->create();
    $a = invIngredient(['supplier_id' => $supplier->id, 'stock' => 100]);
    $b = invIngredient(['supplier_id' => $supplier->id, 'stock' => 100]);
    $order = invOrder($supplier, PurchaseOrderStatus::Sent, [$a->id => 500, $b->id => 500]);
    $seen = 0;
    ActivityLog::creating(function () use (&$seen) {
        if (++$seen === 2) {
            throw new RuntimeException('log unavailable');
        }
    });

    expect(fn () => $this->withoutExceptionHandling()->actingAs(invUser(Role::Manager))
        ->post("/staff/inventory/purchase-orders/{$order->id}/receive"))->toThrow(RuntimeException::class);

    expect($a->fresh()->stock)->toBe(100)->and($b->fresh()->stock)->toBe(100)
        ->and(StockMovement::count())->toBe(0)
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::Sent);
});

it('cancels an open order but not a received one', function () {
    $supplier = Supplier::factory()->create();
    $open = invOrder($supplier, PurchaseOrderStatus::Sent);
    $done = invOrder($supplier, PurchaseOrderStatus::Received);

    $this->actingAs(invUser(Role::Manager));
    $this->post("/staff/inventory/purchase-orders/{$open->id}/cancel")->assertRedirect();
    $this->post("/staff/inventory/purchase-orders/{$done->id}/cancel")->assertSessionHasErrors('status');

    expect($open->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled)->and($done->fresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('lists purchase orders by status', function () {
    $supplier = Supplier::factory()->create();
    invOrder($supplier, PurchaseOrderStatus::Draft);
    invOrder($supplier, PurchaseOrderStatus::Sent);
    invOrder($supplier, PurchaseOrderStatus::Sent);

    $this->actingAs(invUser(Role::Manager))->get('/staff/inventory/purchase-orders?status=sent')->assertInertia(fn ($page) => $page
        ->where('filters.status', 'sent')
        ->where('counts.sent', 2)
        ->loadDeferredProps(fn ($page) => $page->has('orders', 2)));
});

it('downloads a purchase order as a PDF', function () {
    $supplier = Supplier::factory()->create(['name' => 'Lea Valley Dairy']);
    $ingredient = invIngredient(['supplier_id' => $supplier->id]);
    $order = invOrder($supplier, PurchaseOrderStatus::Sent, [$ingredient->id => 2500]);

    $response = $this->actingAs(invUser(Role::Manager))->get("/staff/inventory/purchase-orders/{$order->id}/pdf");

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain("{$order->reference}.pdf")
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('does not offer a PDF for a cancelled order or to front-of-house', function () {
    $supplier = Supplier::factory()->create();
    $cancelled = invOrder($supplier, PurchaseOrderStatus::Cancelled);
    $draft = invOrder($supplier, PurchaseOrderStatus::Draft);

    $this->actingAs(invUser(Role::Manager))->get("/staff/inventory/purchase-orders/{$cancelled->id}/pdf")->assertNotFound();
    $this->actingAs(invUser(Role::Waiter))->get("/staff/inventory/purchase-orders/{$draft->id}/pdf")->assertForbidden();
});

it('refuses to send, edit or cancel an order whose status changed under it', function () {
    $supplier = Supplier::factory()->create();
    $ingredient = invIngredient(['supplier_id' => $supplier->id]);
    $order = invOrder($supplier, PurchaseOrderStatus::Draft, [$ingredient->id => 500]);
    $stale = PurchaseOrder::find($order->id);

    $this->actingAs(invUser(Role::Manager));
    $this->post("/staff/inventory/purchase-orders/{$order->id}/send")->assertRedirect();

    // The same draft is still in memory elsewhere: the locked re-read must see "sent".
    $user = invUser(Role::Manager);
    expect(fn () => app(PurchaseOrders::class)->send($stale, $user))->toThrow(ValidationException::class)
        ->and(fn () => app(PurchaseOrders::class)->replaceLines($stale, [['ingredient_id' => $ingredient->id, 'quantity' => 1]], $user))->toThrow(ValidationException::class);

    $this->post("/staff/inventory/purchase-orders/{$order->id}/cancel")->assertRedirect();
    expect(fn () => app(PurchaseOrders::class)->cancel($stale, $user))->toThrow(ValidationException::class)
        ->and(ActivityLog::where('event', 'inventory.po_sent')->count())->toBe(1)
        ->and($order->fresh()->lines->first()->quantity)->toBe(500);
});

it('sums stock value and counts low items in the database', function () {
    invIngredient(['stock' => 1000, 'low_threshold' => 1000, 'cost_per_thousand' => 500]);
    invIngredient(['stock' => 2500, 'low_threshold' => 100, 'cost_per_thousand' => 1000]);

    $this->actingAs(invUser(Role::Manager))->get('/staff/inventory')->assertInertia(fn ($page) => $page
        ->where('summary.count', 2)->where('summary.low', 1)->where('summary.value', 3000));
});

it('pages the waste list and leaves the week summary unaffected', function () {
    $ingredient = invIngredient(['cost_per_thousand' => 1000]);

    foreach (range(1, 35) as $i) {
        invMovement(['ingredient_id' => $ingredient->id, 'type' => StockMovementType::Waste, 'quantity' => -100, 'reason' => 'Dropped', 'created_at' => now()->subMinutes($i)]);
    }

    $this->actingAs(invUser(Role::Manager))->get('/staff/inventory/waste')->assertInertia(fn ($page) => $page
        ->where('summary.entries', 35)->where('summary.cost', 35 * 100)
        ->loadDeferredProps(fn ($page) => $page->has('entries.data', 30)->where('entries.last_page', 2)->where('entries.total', 35)));
});

it('keeps costs and stock value away from kitchen on every stock page', function () {
    $ingredient = invIngredient(['name' => 'Ghee', 'stock' => 9000, 'cost_per_thousand' => 450]);
    $kitchen = invUser(Role::Kitchen);
    app(StockLedger::class)->waste($ingredient, 500, 'Spilt', $kitchen);

    $this->actingAs($kitchen)->get('/staff/inventory')->assertInertia(fn ($page) => $page
        ->missing('summary.value')
        ->loadDeferredProps(fn ($page) => $page->has('ingredients', 1)->missing('ingredients.0.cost_per_thousand')->missing('ingredients.0.value')));

    $this->actingAs($kitchen)->get('/staff/inventory/waste')->assertInertia(fn ($page) => $page
        ->missing('summary.cost')
        ->missing('summary.top.0.cost')
        ->missing('ingredients.0.cost_per_thousand')
        ->loadDeferredProps(fn ($page) => $page->has('entries.data', 1)->missing('entries.data.0.cost')));

    $this->actingAs($kitchen)->get('/staff/inventory/movements')->assertInertia(fn ($page) => $page
        ->missing('ingredients.0.cost_per_thousand')
        ->loadDeferredProps(fn ($page) => $page->has('movements.data')->missing('movements.data.0.cost')));
});

it('still shows costs and stock value to managers', function () {
    $ingredient = invIngredient(['name' => 'Ghee', 'stock' => 9000, 'cost_per_thousand' => 450]);
    $manager = invUser(Role::Manager);
    app(StockLedger::class)->waste($ingredient, 500, 'Spilt', $manager);

    $this->actingAs($manager)->get('/staff/inventory')->assertInertia(fn ($page) => $page
        ->has('summary.value')
        ->loadDeferredProps(fn ($page) => $page->has('ingredients.0.cost_per_thousand')->has('ingredients.0.value')));

    $this->actingAs($manager)->get('/staff/inventory/waste')->assertInertia(fn ($page) => $page
        ->has('summary.cost')
        ->has('ingredients.0.cost_per_thousand')
        ->loadDeferredProps(fn ($page) => $page->has('entries.data.0.cost')));

    $this->actingAs($manager)->get('/staff/inventory/movements')->assertInertia(fn ($page) => $page
        ->has('ingredients.0.cost_per_thousand')
        ->loadDeferredProps(fn ($page) => $page->has('movements.data.0.cost')));
});

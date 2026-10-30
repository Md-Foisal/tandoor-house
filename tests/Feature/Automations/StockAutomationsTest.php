<?php

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Events\OrderStatusChanged;
use App\Mail\PurchaseOrderToSupplier;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\Staff\LowStock;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function stockDish(Ingredient $ingredient, int $perPortion): MenuItem
{
    $dish = MenuItem::factory()->create(['is_available' => true]);
    $dish->recipeLines()->create(['ingredient_id' => $ingredient->id, 'quantity' => $perPortion]);

    return $dish;
}

function stockOrder(MenuItem $dish, int $quantity): Order
{
    $order = Order::factory()->pickup()->status(OrderStatus::Placed)->create();
    OrderLine::factory()->create(['order_id' => $order->id, 'menu_item_id' => $dish->id, 'item_variant_id' => null, 'quantity' => $quantity]);

    return $order;
}

function moveOrder(Order $order, OrderStatus $to): void
{
    event(new OrderStatusChanged($order, null, $to));
}

function automationsOff(): void
{
    app(Settings::class)->set('automations', ['stock_deduction' => false]);
}

it('deducts recipe quantities times line quantity once when an order is accepted', function () {
    $ingredient = Ingredient::factory()->create(['stock' => 10000, 'low_threshold' => 500, 'par_level' => 12000]);
    $order = stockOrder(stockDish($ingredient, 150), 3);

    moveOrder($order, OrderStatus::Accepted);
    moveOrder($order, OrderStatus::Accepted);

    expect($ingredient->fresh()->stock)->toBe(9550);

    $sale = StockMovement::where('order_id', $order->id)->sole();
    expect($sale->type)->toBe(StockMovementType::Sale)->and($sale->quantity)->toBe(-450);
    expect(ActivityLog::where('event', 'automation.stock.deducted')->count())->toBe(1);
});

it('does nothing when the switch is off', function () {
    automationsOff();
    Notification::fake();
    $ingredient = Ingredient::factory()->create(['stock' => 100, 'low_threshold' => 50, 'par_level' => 1000]);
    $order = stockOrder(stockDish($ingredient, 90), 1);

    moveOrder($order, OrderStatus::Accepted);

    expect($ingredient->fresh()->stock)->toBe(100)->and(StockMovement::count())->toBe(0);
    Notification::assertNothingSent();
});

it('puts stock back when an accepted order is cancelled, once', function (OrderStatus $to) {
    $ingredient = Ingredient::factory()->create(['stock' => 10000, 'low_threshold' => 500, 'par_level' => 12000]);
    $order = stockOrder(stockDish($ingredient, 200), 2);

    moveOrder($order, OrderStatus::Accepted);
    moveOrder($order, $to);
    moveOrder($order, $to);

    expect($ingredient->fresh()->stock)->toBe(10000);
    $back = StockMovement::where('order_id', $order->id)->where('type', StockMovementType::Adjust)->sole();
    expect($back->quantity)->toBe(400);
})->with([OrderStatus::Cancelled, OrderStatus::Rejected]);

it('does not invent stock for an order that was never accepted', function () {
    $ingredient = Ingredient::factory()->create(['stock' => 1000]);
    $order = stockOrder(stockDish($ingredient, 200), 1);

    moveOrder($order, OrderStatus::Rejected);

    expect($ingredient->fresh()->stock)->toBe(1000)->and(StockMovement::count())->toBe(0);
});

it('never takes stock below zero and says how short it was', function () {
    $ingredient = Ingredient::factory()->create(['stock' => 100, 'low_threshold' => 0, 'par_level' => 1000]);
    $order = stockOrder(stockDish($ingredient, 80), 2);

    moveOrder($order, OrderStatus::Accepted);

    expect($ingredient->fresh()->stock)->toBe(0);
    expect(ActivityLog::where('event', 'automation.stock.deducted')->sole()->properties['shortfall'])->toBe(60);
});

it('marks dishes sold out when an ingredient cannot cover a portion, and brings them back on restock', function () {
    $ingredient = Ingredient::factory()->create(['stock' => 500, 'low_threshold' => 100, 'par_level' => 5000]);
    $dish = stockDish($ingredient, 200);
    $other = stockDish(Ingredient::factory()->create(['stock' => 9000]), 100);
    $order = stockOrder($dish, 2);

    moveOrder($order, OrderStatus::Accepted);

    expect($ingredient->fresh()->stock)->toBe(100);
    expect($dish->fresh()->is_available)->toBeFalse()->and($other->fresh()->is_available)->toBeTrue();
    expect(ActivityLog::where('event', 'automation.stock.sold_out')->count())->toBe(1);

    $manager = User::factory()->role(Role::Manager)->create();
    $this->actingAs($manager)->post("/staff/inventory/ingredients/{$ingredient->id}/stock-in", ['quantity' => 5000, 'reason' => 'Delivery'])->assertRedirect();

    expect($dish->fresh()->is_available)->toBeTrue()->and($dish->fresh()->sold_out_at)->toBeNull();
    expect(ActivityLog::where('event', 'automation.stock.back_on')->count())->toBe(1);
});

it('leaves a dish alone that a manager hid by hand', function () {
    $ingredient = Ingredient::factory()->create(['stock' => 100, 'low_threshold' => 10, 'par_level' => 5000]);
    $dish = stockDish($ingredient, 200);
    $dish->update(['is_available' => false]);

    $manager = User::factory()->role(Role::Manager)->create();
    $this->actingAs($manager)->post("/staff/inventory/ingredients/{$ingredient->id}/stock-in", ['quantity' => 5000, 'reason' => 'Delivery'])->assertRedirect();

    expect($dish->fresh()->is_available)->toBeFalse();
});

it('does not bring back a dish a manager hid after the automation did', function () {
    $ingredient = Ingredient::factory()->create(['stock' => 100, 'low_threshold' => 10, 'par_level' => 5000]);
    $dish = stockDish($ingredient, 200);
    $order = stockOrder($dish, 1);
    moveOrder($order, OrderStatus::Accepted);
    expect($dish->fresh()->is_available)->toBeFalse();

    $this->travel(5)->minutes();
    $dish->fresh()->update(['is_available' => true]);
    $this->travel(5)->minutes();
    $dish->fresh()->update(['is_available' => false]);

    $manager = User::factory()->role(Role::Manager)->create();
    $this->actingAs($manager)->post("/staff/inventory/ingredients/{$ingredient->id}/stock-in", ['quantity' => 5000, 'reason' => 'Delivery'])->assertRedirect();

    expect($dish->fresh()->is_available)->toBeFalse();
});

it('alerts managers and the owner once per crossing and drafts one order', function () {
    Notification::fake();
    $supplier = Supplier::factory()->create();
    $ingredient = Ingredient::factory()->create(['supplier_id' => $supplier->id, 'stock' => 1000, 'low_threshold' => 500, 'par_level' => 4000]);
    $manager = User::factory()->role(Role::Manager)->create();
    $owner = User::factory()->role(Role::Owner)->create();
    $waiter = User::factory()->role(Role::Waiter)->create();
    $dish = stockDish($ingredient, 100);

    moveOrder(stockOrder($dish, 5), OrderStatus::Accepted);
    moveOrder(stockOrder($dish, 1), OrderStatus::Accepted);

    Notification::assertSentTo([$manager, $owner], LowStock::class);
    Notification::assertNotSentTo($waiter, LowStock::class);
    Notification::assertSentToTimes($manager, LowStock::class, 1);

    $draft = PurchaseOrder::where('supplier_id', $supplier->id)->sole();
    expect($draft->status)->toBe(PurchaseOrderStatus::Draft);
    $line = $draft->lines()->sole();
    expect($line->ingredient_id)->toBe($ingredient->id)->and($line->quantity)->toBe(4000 - 500);
    expect(ActivityLog::where('event', 'automation.stock.low')->count())->toBe(1);
});

it('adds a second low ingredient to the supplier draft instead of starting another', function () {
    Notification::fake();
    $supplier = Supplier::factory()->create();
    $a = Ingredient::factory()->create(['supplier_id' => $supplier->id, 'stock' => 1000, 'low_threshold' => 500, 'par_level' => 3000]);
    $b = Ingredient::factory()->create(['supplier_id' => $supplier->id, 'stock' => 800, 'low_threshold' => 400, 'par_level' => 2000]);

    moveOrder(stockOrder(stockDish($a, 600), 1), OrderStatus::Accepted);
    moveOrder(stockOrder(stockDish($b, 500), 1), OrderStatus::Accepted);

    $draft = PurchaseOrder::sole();
    expect($draft->lines()->pluck('quantity', 'ingredient_id')->all())->toBe([$a->id => 2600, $b->id => 1700]);
});

it('alerts without an order when the ingredient has no supplier', function () {
    Notification::fake();
    $manager = User::factory()->role(Role::Manager)->create();
    $ingredient = Ingredient::factory()->create(['supplier_id' => null, 'stock' => 600, 'low_threshold' => 500, 'par_level' => 3000]);

    moveOrder(stockOrder(stockDish($ingredient, 200), 1), OrderStatus::Accepted);

    Notification::assertSentTo($manager, LowStock::class);
    expect(PurchaseOrder::count())->toBe(0);
});

it('emails the supplier the purchase order when staff send it', function () {
    Mail::fake();
    $supplier = Supplier::factory()->create(['email' => 'orders@spice.test']);
    $ingredient = Ingredient::factory()->create(['supplier_id' => $supplier->id]);
    $po = PurchaseOrder::create(['reference' => 'PO-TEST-0001', 'supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Draft, 'total' => 0]);
    $po->lines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 2000, 'unit_cost' => 500]);

    $this->actingAs(User::factory()->role(Role::Manager)->create())->post("/staff/inventory/purchase-orders/{$po->id}/send")->assertRedirect();

    Mail::assertQueued(PurchaseOrderToSupplier::class, fn ($mail) => $mail->hasTo('orders@spice.test') && $mail->hasReplyTo(config('restaurant.contact.email')));
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Sent);
    expect(ActivityLog::where('event', 'inventory.po_emailed')->count())->toBe(1);
});

it('builds the supplier email with the PDF attached', function () {
    $supplier = Supplier::factory()->create(['email' => 'orders@spice.test']);
    $ingredient = Ingredient::factory()->create(['supplier_id' => $supplier->id]);
    $po = PurchaseOrder::create(['reference' => 'PO-TEST-0002', 'supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Sent, 'total' => 0]);
    $po->lines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 2000, 'unit_cost' => 500]);

    (new PurchaseOrderToSupplier($po))->assertSeeInHtml('PO-TEST-0002');

    Mail::to('orders@spice.test')->send(new PurchaseOrderToSupplier($po));

    $sent = Mail::mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();
    $attachment = $sent->getAttachments()[0];
    expect($attachment->getFilename())->toBe('PO-TEST-0002.pdf')->and(substr($attachment->getBody(), 0, 4))->toBe('%PDF');
});

it('still marks the order sent and shows a note when the supplier has no email', function () {
    Mail::fake();
    $supplier = Supplier::factory()->create(['email' => null]);
    $ingredient = Ingredient::factory()->create(['supplier_id' => $supplier->id]);
    $po = PurchaseOrder::create(['reference' => 'PO-TEST-0003', 'supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Draft, 'total' => 0]);
    $po->lines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 2000, 'unit_cost' => 500]);

    $this->actingAs(User::factory()->role(Role::Manager)->create())->post("/staff/inventory/purchase-orders/{$po->id}/send")->assertRedirect();

    Mail::assertNothingQueued();
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Sent);
});

it('still emails the supplier when the automations are off, because a person chose to send it', function () {
    automationsOff();
    Mail::fake();
    $supplier = Supplier::factory()->create(['email' => 'orders@spice.test']);
    $ingredient = Ingredient::factory()->create(['supplier_id' => $supplier->id]);
    $po = PurchaseOrder::create(['reference' => 'PO-TEST-0004', 'supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Draft, 'total' => 0]);
    $po->lines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 2000, 'unit_cost' => 500]);

    $this->actingAs(User::factory()->role(Role::Manager)->create())->post("/staff/inventory/purchase-orders/{$po->id}/send")->assertRedirect();

    Mail::assertQueued(PurchaseOrderToSupplier::class);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Sent);
});

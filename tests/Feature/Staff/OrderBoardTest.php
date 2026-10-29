<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\User;

function staffAs(Role $role): User
{
    return User::factory()->role($role)->create();
}

function orderWith(array $state = [], int $unitPrice = 1000, int $quantity = 2): Order
{
    $order = Order::factory()->withPayment()->create($state);
    OrderLine::factory()->for($order)->forItem(MenuItem::factory()->create(['price' => $unitPrice]))->create(['quantity' => $quantity]);

    $order->payments()->update(['amount' => $order->refresh()->total]);

    return $order->refresh();
}

it('lets cashiers, waiters, managers and owners onto the board and nobody else', function (Role $role, int $status) {
    $this->actingAs(staffAs($role))->get('/staff/orders')->assertStatus($status);
})->with([
    [Role::Cashier, 200], [Role::Waiter, 200], [Role::Manager, 200], [Role::Owner, 200],
    [Role::Kitchen, 403], [Role::Rider, 403], [Role::Customer, 403],
]);

it('keeps guests away from the board and every action', function () {
    $order = orderWith();

    $this->get('/staff/orders')->assertRedirect('/login');
    $this->post("/staff/orders/{$order->number}/status", ['status' => 'accepted'])->assertRedirect('/login');
    $this->get("/staff/orders/{$order->number}/receipt.pdf")->assertRedirect('/login');
});

it('keeps kitchen, riders and customers from acting on an order', function (Role $role) {
    $order = orderWith();
    $this->actingAs(staffAs($role));

    $this->post("/staff/orders/{$order->number}/status", ['status' => 'accepted'])->assertForbidden();
    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'full', 'reason' => 'Nope'])->assertForbidden();
    $this->get("/staff/orders/{$order->number}")->assertForbidden();
    expect($order->refresh()->status)->toBe(OrderStatus::Placed);
})->with([Role::Kitchen, Role::Rider, Role::Customer]);

it('puts live and today orders on the board with next steps', function () {
    $new = orderWith();
    $old = Order::factory()->completed()->create(['placed_at' => now()->subDays(2)]);

    $this->actingAs(staffAs(Role::Cashier))->get('/staff/orders')
        ->assertInertia(fn ($page) => $page
            ->component('staff/orders/index')
            ->has('orders', 1)
            ->where('orders.0.number', $new->number)
            ->where('orders.0.next', 'accepted')
            ->where('orders.0.can_reject', true));

    expect($old->number)->not->toBe($new->number);
});

it('moves an order through accept and cooking and logs the accept', function () {
    $order = orderWith();
    $cashier = staffAs(Role::Cashier);

    $this->actingAs($cashier)->post("/staff/orders/{$order->number}/status", ['status' => 'accepted'])->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::Accepted);

    $this->post("/staff/orders/{$order->number}/status", ['status' => 'cooking'])->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::Cooking)
        ->and($order->events()->count())->toBe(2)
        ->and(ActivityLog::where('event', 'order.accepted')->where('user_id', $cashier->id)->exists())->toBeTrue();
});

it('needs a reason to reject or cancel', function () {
    $order = orderWith();
    $this->actingAs(staffAs(Role::Waiter));

    $this->post("/staff/orders/{$order->number}/status", ['status' => 'rejected'])->assertSessionHasErrors('reason');
    expect($order->refresh()->status)->toBe(OrderStatus::Placed);

    $this->post("/staff/orders/{$order->number}/status", ['status' => 'rejected', 'reason' => 'We are out of lamb.'])->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe(OrderStatus::Rejected)
        ->and($order->rejection_reason)->toBe('We are out of lamb.');
});

it('refuses a jump the state machine does not allow', function () {
    $order = orderWith();

    $this->actingAs(staffAs(Role::Manager))
        ->post("/staff/orders/{$order->number}/status", ['status' => 'completed'])
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe(OrderStatus::Placed);
});

it('edits quantities, removes a line and recomputes totals before cooking', function () {
    $order = orderWith(unitPrice: 1000, quantity: 2);
    $second = OrderLine::factory()->for($order)->forItem(MenuItem::factory()->create(['price' => 500]))->create(['quantity' => 1]);
    $order->refresh();
    $order->payments()->delete();
    $order->forceFill(['payment_status' => PaymentStatus::Pending])->save();
    $first = $order->lines()->orderBy('id')->first();

    $this->actingAs(staffAs(Role::Cashier))
        ->patch("/staff/orders/{$order->number}/lines", [
            'lines' => [['id' => $first->id, 'quantity' => 3], ['id' => $second->id, 'quantity' => 0]],
            'note' => 'No coriander',
        ])->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->lines()->count())->toBe(1)
        ->and($order->subtotal)->toBe(3000)
        ->and($order->total)->toBe(3000)
        ->and($order->notes)->toContain('No coriander')
        ->and(ActivityLog::where('event', 'order.edited')->exists())->toBeTrue();
});

it('will not edit once cooking has started, or empty an order', function () {
    $order = orderWith(['status' => OrderStatus::Cooking]);
    $line = $order->lines()->first();

    $this->actingAs(staffAs(Role::Manager))
        ->patch("/staff/orders/{$order->number}/lines", ['lines' => [['id' => $line->id, 'quantity' => 1]]])
        ->assertSessionHasErrors('order');

    $placed = orderWith();
    $this->patch("/staff/orders/{$placed->number}/lines", ['lines' => [['id' => $placed->lines()->first()->id, 'quantity' => 0]]])
        ->assertSessionHasErrors('lines');
});

it('stops a paid order from growing when edited', function () {
    $order = orderWith(quantity: 1);

    $this->actingAs(staffAs(Role::Cashier))
        ->patch("/staff/orders/{$order->number}/lines", ['lines' => [['id' => $order->lines()->first()->id, 'quantity' => 4]]])
        ->assertSessionHasErrors('lines');
});

it('lets a cashier refund up to fifty pounds and no more', function () {
    $order = orderWith(unitPrice: 4000, quantity: 3);
    $this->actingAs(staffAs(Role::Cashier));

    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'amount', 'amount' => 5100, 'reason' => 'Cold food'])->assertForbidden();
    expect(Payment::first()->refunded)->toBe(0);

    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'amount', 'amount' => 5000, 'reason' => 'Cold food'])->assertSessionHasNoErrors();
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and(Payment::first()->refunded)->toBe(5000);

    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'amount', 'amount' => 100, 'reason' => 'More'])->assertForbidden();
});

it('lets managers and owners refund any amount up to what was paid', function (Role $role) {
    $order = orderWith(unitPrice: 4000, quantity: 3);
    $this->actingAs(staffAs($role));

    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'amount', 'amount' => $order->total + 1, 'reason' => 'Too much'])
        ->assertSessionHasErrors('amount');

    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'full', 'reason' => 'Order never arrived'])->assertSessionHasNoErrors();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(Payment::first()->status)->toBe(PaymentStatus::Refunded)
        ->and(ActivityLog::where('event', 'order.refunded')->count())->toBe(1);
})->with([Role::Manager, Role::Owner]);

it('refunds by line and needs a reason', function () {
    $order = orderWith(unitPrice: 1500, quantity: 2);
    $line = $order->lines()->first();
    $this->actingAs(staffAs(Role::Manager));

    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'lines', 'lines' => [$line->id => 1]])->assertSessionHasErrors('reason');
    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'lines', 'lines' => [$line->id => 1], 'reason' => 'One was wrong'])->assertSessionHasNoErrors();

    expect(Payment::first()->refunded)->toBe(1500);
});

it('does not let waiters refund or refund an unpaid order', function () {
    $order = orderWith();
    $this->actingAs(staffAs(Role::Waiter))
        ->post("/staff/orders/{$order->number}/refund", ['kind' => 'full', 'reason' => 'Test'])->assertForbidden();

    $unpaid = orderWith(['payment_status' => PaymentStatus::Pending]);
    $unpaid->payments()->delete();
    $this->actingAs(staffAs(Role::Manager))
        ->post("/staff/orders/{$unpaid->number}/refund", ['kind' => 'full', 'reason' => 'Test'])->assertSessionHasErrors('amount');
});

it('shows the receipt with the right totals and downloads a pdf', function () {
    $order = orderWith(['tip' => 0], unitPrice: 1250, quantity: 2);
    $this->actingAs(staffAs(Role::Cashier));

    $this->get("/staff/orders/{$order->number}/receipt")
        ->assertOk()
        ->assertSee($order->number)
        ->assertSee('£'.number_format($order->total / 100, 2))
        ->assertSee('£'.number_format($order->vat_total / 100, 2))
        ->assertSee(config('restaurant.company.vat_number'));

    $pdf = $this->get("/staff/orders/{$order->number}/receipt.pdf");
    $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($pdf->headers->get('content-disposition'))->toContain("receipt-{$order->number}.pdf")
        ->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
});

it('assigns a rider to a delivery order only', function () {
    $rider = staffAs(Role::Rider);
    $delivery = Order::factory()->delivery()->live(OrderStatus::Ready)->create();
    $pickup = Order::factory()->create();
    $this->actingAs(staffAs(Role::Cashier));

    $this->patch("/staff/orders/{$delivery->number}/rider", ['rider_id' => $rider->id])->assertSessionHasNoErrors();
    expect($delivery->refresh()->rider_id)->toBe($rider->id);

    $this->patch("/staff/orders/{$pickup->number}/rider", ['rider_id' => $rider->id])->assertForbidden();
    $this->patch("/staff/orders/{$delivery->number}/rider", ['rider_id' => staffAs(Role::Waiter)->id])->assertSessionHasErrors('rider_id');
});

it('filters by channel and status and searches number, name, phone and table', function () {
    $pickup = orderWith(['contact' => ['name' => 'Priya Nair', 'email' => 'p@example.test', 'phone' => '07123 456789']]);
    $delivery = Order::factory()->delivery()->live(OrderStatus::Ready)->create(['contact' => ['name' => 'Tom Hall', 'email' => 't@example.test', 'phone' => '07999 111222']]);
    $dineIn = Order::factory()->dineIn()->create();
    $dineIn->diningTable->update(['label' => 'T14']);
    $this->actingAs(staffAs(Role::Manager));

    $numbers = fn (string $query) => collect($this->get('/staff/orders?'.$query)->viewData('page')['props']['orders'])->pluck('number')->all();

    expect($numbers(''))->toHaveCount(3)
        ->and($numbers('channel=delivery'))->toBe([$delivery->number])
        ->and($numbers('status=ready'))->toBe([$delivery->number])
        ->and($numbers('q=priya'))->toBe([$pickup->number])
        ->and($numbers('q=07123456789'))->toBe([$pickup->number])
        ->and($numbers('q='.$delivery->number))->toBe([$delivery->number])
        ->and($numbers('q=t14'))->toBe([$dineIn->number])
        ->and($numbers('q=nobody-here'))->toBe([]);
});

it('shows the order detail with timeline, contact and abilities', function () {
    $order = orderWith(['status' => OrderStatus::Accepted]);
    $order->events()->forceCreate(['from' => 'placed', 'to' => 'accepted']);

    $this->actingAs(staffAs(Role::Cashier))->get("/staff/orders/{$order->number}")
        ->assertInertia(fn ($page) => $page
            ->component('staff/orders/show')
            ->where('order.number', $order->number)
            ->has('order.events', 1)
            ->where('abilities.edit', true)
            ->where('abilities.refund', true)
            ->where('abilities.refund_limit', 5000));
});

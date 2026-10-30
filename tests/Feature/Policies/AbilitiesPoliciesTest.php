<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\TipAllocation;
use App\Models\User;
use Tests\Feature\Policies\RoleMatrix;

/*
| The policies on their own, without the "staff.<area>" route gates in front,
| so a policy that is too generous fails here even though the route still
| answers 403.
*/
dataset('policy abilities', [
    'category create' => ['create', fn () => Category::class, RoleMatrix::MANAGEMENT],
    'dish update' => ['update', fn () => MenuItem::factory()->create(), RoleMatrix::MANAGEMENT],
    'dish VAT rate' => ['setVatRate', fn () => MenuItem::class, RoleMatrix::OWNER],
    'dish sold out' => ['markSoldOut', fn () => MenuItem::factory()->create(), RoleMatrix::KITCHEN],
    'recipe' => ['updateRecipe', fn () => MenuItem::factory()->create(), RoleMatrix::MANAGEMENT],
    'ingredient create' => ['create', fn () => Ingredient::class, RoleMatrix::MANAGEMENT],
    'stock in' => ['stockIn', fn () => Ingredient::factory()->create(), RoleMatrix::KITCHEN],
    'waste' => ['logWaste', fn () => Ingredient::class, RoleMatrix::KITCHEN],
    'supplier create' => ['create', fn () => Supplier::class, RoleMatrix::MANAGEMENT],
    'purchase order create' => ['create', fn () => PurchaseOrder::class, RoleMatrix::MANAGEMENT],
    'table merge' => ['merge', fn () => DiningTable::class, RoleMatrix::FLOOR],
    'staff create' => ['create', fn () => User::class, RoleMatrix::MANAGEMENT],
    'staff edit a waiter' => ['update', fn () => User::factory()->role(Role::Waiter)->create(), RoleMatrix::MANAGEMENT],
    'staff edit a customer' => ['update', fn () => User::factory()->create(), []],
    'staff remove a waiter' => ['delete', fn () => User::factory()->role(Role::Waiter)->create(), RoleMatrix::OWNER],
    'shift create' => ['create', fn () => Shift::class, RoleMatrix::MANAGEMENT],
    'clock in' => ['clock', fn () => Attendance::class, RoleMatrix::STAFF],
    'tips pay' => ['pay', fn () => TipAllocation::class, RoleMatrix::OWNER],
    'review moderate' => ['moderate', fn () => Review::class, RoleMatrix::MANAGEMENT],
    'settings' => ['update', fn () => Setting::class, RoleMatrix::MANAGEMENT],
    'money settings' => ['updateMoney', fn () => Setting::class, RoleMatrix::OWNER],
    'activity export' => ['export', fn () => ActivityLog::class, RoleMatrix::MANAGEMENT],
    'take payment at a table' => ['create', fn () => [Payment::class, Order::factory()->dineIn()->live()->create()], RoleMatrix::FLOOR],
    'take payment for a pickup' => ['create', fn () => [Payment::class, Order::factory()->live()->create()], ['cashier', 'manager', 'owner']],
    'refund' => ['refund', fn () => [Payment::class, Order::factory()->create()], ['cashier', 'manager', 'owner']],
    'order move' => ['staff.orders.move', fn () => Order::factory()->live()->create(), RoleMatrix::FLOOR],
    'order edit' => ['staff.orders.edit', fn () => Order::factory()->live()->create(), RoleMatrix::FLOOR],
    'order rider' => ['staff.orders.assign-rider', fn () => Order::factory()->delivery()->live(OrderStatus::Ready)->create(), RoleMatrix::MANAGEMENT],
    'kitchen cook' => ['staff.kitchen.cook', fn () => [], RoleMatrix::KITCHEN],
    'unassigned delivery' => ['staff.deliveries.act', fn () => Order::factory()->delivery()->create(), RoleMatrix::MANAGEMENT],
    'booking' => ['staff.reservations.book', fn () => [], RoleMatrix::FLOOR],
    'booking no-show' => ['staff.reservations.transition', fn () => [Reservation::factory()->create(), 'no_show'], RoleMatrix::FLOOR],
    'booking deposit confirm' => ['staff.reservations.transition', fn () => [Reservation::factory()->pendingDeposit()->create(), 'confirm'], RoleMatrix::MANAGEMENT],
    'booking seat' => ['staff.reservations.seat', fn () => Reservation::factory()->create(), RoleMatrix::FLOOR],
]);

it('grants each ability to exactly the roles in the plan', function (string $ability, Closure $arguments, array $allowed) {
    $subject = $arguments();

    foreach (['customer', ...RoleMatrix::STAFF] as $role) {
        $user = RoleMatrix::actor($role);

        expect($user->can($ability, $subject))->toBe(in_array($role, $allowed, true), "{$role} → {$ability}");
    }
})->with('policy abilities');

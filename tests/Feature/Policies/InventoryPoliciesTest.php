<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Tests\Feature\Policies\RoleMatrix;

function policyPurchaseOrder(PurchaseOrderStatus $status = PurchaseOrderStatus::Draft): PurchaseOrder
{
    return PurchaseOrder::create([
        'reference' => 'PO-2026-'.fake()->unique()->numerify('####'),
        'supplier_id' => Supplier::factory()->create()->id,
        'status' => $status,
        'total' => 0,
    ]);
}

dataset('policy inventory writes', [
    'add an ingredient' => ['post', fn () => '/staff/inventory/ingredients', RoleMatrix::MANAGEMENT],
    'edit an ingredient' => ['patch', fn () => '/staff/inventory/ingredients/'.Ingredient::factory()->create()->id, RoleMatrix::MANAGEMENT],
    'stock in' => ['post', fn () => '/staff/inventory/ingredients/'.Ingredient::factory()->create()->id.'/stock-in', RoleMatrix::KITCHEN],
    'count stock' => ['post', fn () => '/staff/inventory/ingredients/'.Ingredient::factory()->create()->id.'/adjust', RoleMatrix::KITCHEN],
    'log waste' => ['post', fn () => '/staff/inventory/waste', RoleMatrix::KITCHEN],
    'edit a recipe' => ['put', fn () => '/staff/inventory/recipes/'.MenuItem::factory()->create()->id, RoleMatrix::MANAGEMENT],
    'see suppliers' => ['get', fn () => '/staff/inventory/suppliers', RoleMatrix::MANAGEMENT],
    'add a supplier' => ['post', fn () => '/staff/inventory/suppliers', RoleMatrix::MANAGEMENT],
    'edit a supplier' => ['patch', fn () => '/staff/inventory/suppliers/'.Supplier::factory()->create()->id, RoleMatrix::MANAGEMENT],
    'see purchase orders' => ['get', fn () => '/staff/inventory/purchase-orders', RoleMatrix::MANAGEMENT],
    'open a purchase order' => ['get', fn () => '/staff/inventory/purchase-orders/'.policyPurchaseOrder()->id, RoleMatrix::MANAGEMENT],
    'start a purchase order' => ['post', fn () => '/staff/inventory/purchase-orders', RoleMatrix::MANAGEMENT],
    'edit purchase order lines' => ['put', fn () => '/staff/inventory/purchase-orders/'.policyPurchaseOrder()->id.'/lines', RoleMatrix::MANAGEMENT],
    'download a purchase order' => ['get', fn () => '/staff/inventory/purchase-orders/'.policyPurchaseOrder()->id.'/pdf', RoleMatrix::MANAGEMENT],
    'send a purchase order' => ['post', fn () => '/staff/inventory/purchase-orders/'.policyPurchaseOrder()->id.'/send', RoleMatrix::MANAGEMENT],
    'receive a purchase order' => ['post', fn () => '/staff/inventory/purchase-orders/'.policyPurchaseOrder(PurchaseOrderStatus::Sent)->id.'/receive', RoleMatrix::MANAGEMENT],
    'cancel a purchase order' => ['post', fn () => '/staff/inventory/purchase-orders/'.policyPurchaseOrder()->id.'/cancel', RoleMatrix::MANAGEMENT],
]);

it('guards every stock write by role', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy inventory writes')->with(RoleMatrix::ROLES);

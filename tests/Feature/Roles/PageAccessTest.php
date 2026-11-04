<?php

use App\Enums\PaymentStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\Role;
use App\Enums\StaffArea;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\Reservation;
use App\Models\Supplier;
use App\Models\TableBillPayment;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Policies\RoleMatrix;

/*
 * Read side of the role matrix: every GET route, as every role. An expectation
 * is a status code, or 'login' (redirect to sign in), or 'away' (a signed-in
 * person bounced off a guest-only page). Adding a GET route without classifying
 * it here fails `every GET route is classified`.
 */

const PAGE_OPEN = 'open';
const PAGE_SIGNED_IN = 'signed-in';
const PAGE_GUEST_ONLY = 'guest-only';
const PAGE_CONFIRM_PASSWORD = 'confirm-password';
const PAGE_VIEW_RECORD = 'view-record';
const PAGE_OWNER_ONLY = 'owner-only';
const PAGE_UNVERIFIED_ONLY = 'unverified-only';
const PAGE_SETTINGS_REDIRECT = 'settings-redirect';
const PAGE_EMPTY_BASKET = 'empty-basket';
const PAGE_MANAGEMENT = 'management';

/** Routes that are framework plumbing or answer to something other than a role. */
function pageAccessIgnored(): array
{
    return [
        '_inertia/devtools/entries',
        '_inertia/devtools/entries/{id}',
        'broadcasting/auth',
        'storage/{path}',
        'up',
        // Signed link and password-confirmation internals: covered by Auth tests.
        'email/verify/{id}/{hash}',
        'reset-password/{token}',
        'passkeys/confirm/options',
        'passkeys/login/options',
        'user/passkeys/options',
        'user/two-factor-qr-code',
        'user/two-factor-recovery-codes',
        'user/two-factor-secret-key',
        'user/confirmed-password-status',
        'two-factor-challenge',
        'invite/{token}',
    ];
}

/**
 * @return array<string, array{0: Closure(): string, 1: string|StaffArea}>
 */
function pageAccessMatrix(): array
{
    $order = fn () => Order::factory()->pickup()->live()->withLines(1)->create();
    $reservation = fn () => Reservation::factory()->create();
    $table = fn () => DiningTable::factory()->create();
    $purchaseOrder = fn () => PurchaseOrder::create([
        'reference' => 'PO-2026-'.fake()->unique()->numerify('####'),
        'supplier_id' => Supplier::factory()->create()->id,
        'status' => PurchaseOrderStatus::Draft,
        'total' => 0,
    ]);

    $otherTablesPayment = fn () => TableBillPayment::query()->create([
        'dining_table_id' => DiningTable::factory()->create()->id,
        'amount' => 1000,
        'tip' => 0,
        'service_included' => true,
        'payment_status' => PaymentStatus::Pending,
    ]);

    return [
        // The public site.
        '/' => [fn () => '/', PAGE_OPEN],
        'story' => [fn () => '/story', PAGE_OPEN],
        'visit' => [fn () => '/visit', PAGE_OPEN],
        'allergens' => [fn () => '/allergens', PAGE_OPEN],
        'menu' => [fn () => '/menu', PAGE_OPEN],
        'menu/{item}' => [fn () => '/menu/'.MenuItem::factory()->create()->slug, PAGE_OPEN],
        'basket' => [fn () => '/basket', PAGE_OPEN],
        'checkout' => [fn () => '/checkout', PAGE_EMPTY_BASKET],
        'book' => [fn () => '/book', PAGE_OPEN],
        'book/slots' => [fn () => '/book/slots?date='.now()->addDay()->toDateString().'&party=2', PAGE_OPEN],
        'styleguide' => [fn () => '/styleguide', PAGE_OPEN],
        'styleguide/motion' => [fn () => '/styleguide/motion', PAGE_OPEN],
        'sitemap.xml' => [fn () => '/sitemap.xml', PAGE_OPEN],
        'robots.txt' => [fn () => '/robots.txt', PAGE_OPEN],
        'privacy' => [fn () => '/privacy', PAGE_OPEN],
        'cookies' => [fn () => '/cookies', PAGE_OPEN],
        'staff/manifest.webmanifest' => [fn () => '/staff/manifest.webmanifest', PAGE_OPEN],
        'staff/offline' => [fn () => '/staff/offline', PAGE_OPEN],
        '.well-known/passkey-endpoints' => [fn () => '/.well-known/passkey-endpoints', PAGE_OPEN],

        // Someone's own order, booking or table: owner or staff only. Tables are open to anyone holding the QR code.
        'orders/{order}' => [fn () => '/orders/'.$order()->number, PAGE_VIEW_RECORD],
        'orders/{order}/pay' => [fn () => '/orders/'.$order()->number.'/pay', PAGE_OWNER_ONLY],
        'book/{reservation}' => [fn () => '/book/'.$reservation()->reference, PAGE_VIEW_RECORD],
        'book/{reservation}/pay' => [fn () => '/book/'.$reservation()->reference.'/pay', PAGE_OWNER_ONLY],
        't/{table}' => [fn () => '/t/'.$table()->qr_token, PAGE_OPEN],
        't/{table}/bill' => [fn () => '/t/'.$table()->qr_token.'/bill', PAGE_OPEN],
        't/{table}/pay/{payment}' => [fn () => '/t/'.$table()->qr_token.'/pay/'.$otherTablesPayment()->id, PAGE_OWNER_ONLY],

        // Signing in and registering.
        'login' => [fn () => '/login', PAGE_GUEST_ONLY],
        'register' => [fn () => '/register', PAGE_GUEST_ONLY],
        'forgot-password' => [fn () => '/forgot-password', PAGE_GUEST_ONLY],
        'email/verify' => [fn () => '/email/verify', PAGE_UNVERIFIED_ONLY],
        'user/confirm-password' => [fn () => '/user/confirm-password', PAGE_SIGNED_IN],

        // The signed-in account.
        'account' => [fn () => '/account', PAGE_SIGNED_IN],
        'account/addresses' => [fn () => '/account/addresses', PAGE_SIGNED_IN],
        'account/orders' => [fn () => '/account/orders', PAGE_SIGNED_IN],
        'account/data-export' => [fn () => '/account/data-export', PAGE_SIGNED_IN],
        'account/reviews' => [fn () => '/account/reviews', PAGE_SIGNED_IN],
        'settings' => [fn () => '/settings', PAGE_SETTINGS_REDIRECT],
        'settings/profile' => [fn () => '/settings/profile', PAGE_SIGNED_IN],
        'settings/appearance' => [fn () => '/settings/appearance', PAGE_SIGNED_IN],
        'settings/security' => [fn () => '/settings/security', PAGE_CONFIRM_PASSWORD],

        // The staff panel, by area.
        'staff' => [fn () => '/staff', StaffArea::Today],
        'staff/orders' => [fn () => '/staff/orders', StaffArea::Orders],
        'staff/orders/{order}' => [fn () => '/staff/orders/'.$order()->number, StaffArea::Orders],
        'staff/orders/{order}/receipt' => [fn () => '/staff/orders/'.$order()->number.'/receipt', StaffArea::Orders],
        'staff/orders/{order}/receipt.pdf' => [fn () => '/staff/orders/'.$order()->number.'/receipt.pdf', StaffArea::Orders],
        'staff/kitchen' => [fn () => '/staff/kitchen', StaffArea::Kitchen],
        'staff/floor' => [fn () => '/staff/floor', StaffArea::Floor],
        'staff/reservations' => [fn () => '/staff/reservations', StaffArea::Reservations],
        'staff/deliveries' => [fn () => '/staff/deliveries', StaffArea::Deliveries],
        'staff/menu' => [fn () => '/staff/menu', StaffArea::Menu],
        'staff/menu/items/create' => [fn () => '/staff/menu/items/create', StaffArea::Menu],
        'staff/menu/items/{item}/edit' => [fn () => '/staff/menu/items/'.MenuItem::factory()->create()->id.'/edit', StaffArea::Menu],
        'staff/inventory' => [fn () => '/staff/inventory', StaffArea::Inventory],
        'staff/inventory/movements' => [fn () => '/staff/inventory/movements', StaffArea::Inventory],
        'staff/inventory/purchase-orders' => [fn () => '/staff/inventory/purchase-orders', PAGE_MANAGEMENT],
        'staff/inventory/purchase-orders/{purchaseOrder}' => [fn () => '/staff/inventory/purchase-orders/'.$purchaseOrder()->id, PAGE_MANAGEMENT],
        'staff/inventory/purchase-orders/{purchaseOrder}/pdf' => [fn () => '/staff/inventory/purchase-orders/'.$purchaseOrder()->id.'/pdf', PAGE_MANAGEMENT],
        'staff/inventory/recipes' => [fn () => '/staff/inventory/recipes', PAGE_MANAGEMENT],
        'staff/inventory/suppliers' => [fn () => '/staff/inventory/suppliers', PAGE_MANAGEMENT],
        'staff/inventory/waste' => [fn () => '/staff/inventory/waste', StaffArea::Inventory],
        'staff/team' => [fn () => '/staff/team', StaffArea::Team],
        'staff/team/attendance/export' => [fn () => '/staff/team/attendance/export', StaffArea::Team],
        'staff/team/tips/export' => [fn () => '/staff/team/tips/export', StaffArea::Team],
        'staff/reports' => [fn () => '/staff/reports', StaffArea::Reports],
        'staff/reports/export/{section}' => [fn () => '/staff/reports/export/overview', StaffArea::Reports],
        'staff/reports/pdf' => [fn () => '/staff/reports/pdf', StaffArea::Reports],
        'staff/reviews' => [fn () => '/staff/reviews', StaffArea::Reviews],
        'staff/settings' => [fn () => '/staff/settings', StaffArea::Settings],
        'staff/activity' => [fn () => '/staff/activity', StaffArea::Activity],
        'staff/activity/export' => [fn () => '/staff/activity/export', StaffArea::Activity],
    ];
}

/**
 * What the request should answer for a role. Records belong to somebody else,
 * since each role gets a fresh user.
 */
function pageAccessExpected(string|StaffArea $kind, string $role): string|int
{
    $staff = in_array($role, RoleMatrix::STAFF, true);

    if ($kind instanceof StaffArea) {
        return match (true) {
            $role === 'guest' => 'login',
            $staff && $kind->allows(Role::from($role)) => 200,
            default => 403,
        };
    }

    return match ($kind) {
        PAGE_OPEN => 200,
        PAGE_SIGNED_IN => $role === 'guest' ? 'login' : 200,
        PAGE_GUEST_ONLY => $role === 'guest' ? 200 : 'away',
        PAGE_CONFIRM_PASSWORD => $role === 'guest' ? 'login' : 'confirm',
        PAGE_VIEW_RECORD => $staff ? 200 : 403,
        PAGE_OWNER_ONLY => 403,
        PAGE_UNVERIFIED_ONLY => $role === 'guest' ? 'login' : 'away',
        PAGE_SETTINGS_REDIRECT => $role === 'guest' ? 'login' : 'settings',
        PAGE_EMPTY_BASKET => 'basket',
        PAGE_MANAGEMENT => match (true) {
            $role === 'guest' => 'login',
            in_array($role, RoleMatrix::MANAGEMENT, true) => 200,
            default => 403,
        },
    };
}

function assertPageAccess($test, string $role, string $path, string|int $expected): void
{
    $actor = RoleMatrix::actor($role);

    if ($actor !== null) {
        // A real request loads the row; a factory model lacks database defaults.
        $test->actingAs($actor->refresh());
    }

    $response = $test->get($path);

    match ($expected) {
        'login' => $response->assertRedirect('/login'),
        'away' => expect($response->getStatusCode())->toBe(302)->and($response->headers->get('Location'))->not->toContain('/login'),
        'confirm' => $response->assertRedirect(route('password.confirm')),
        'settings' => $response->assertRedirect('/settings/profile'),
        'basket' => $response->assertRedirect(route('basket')),
        default => expect($response->getStatusCode())->toBe($expected, "{$role} on GET {$path}"),
    };
}

dataset('page routes', fn () => pageAccessMatrix());

it('every GET route is classified', function () {
    $classified = array_merge(array_keys(pageAccessMatrix()), pageAccessIgnored());

    $unclassified = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->map(fn ($route) => $route->uri())
        ->reject(fn ($uri) => in_array($uri, $classified, true))
        ->values()
        ->all();

    expect($unclassified)->toBe([], 'Classify these GET routes in tests/Feature/Roles/PageAccessTest.php');
});

it('keeps the matrix free of routes that no longer exist', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->all();
    $stale = array_values(array_filter(
        array_keys(pageAccessMatrix()),
        fn ($uri) => ! in_array($uri, $uris, true),
    ));

    expect($stale)->toBe([]);
});

it('answers each page as each role expects', function (Closure $path, string|StaffArea $kind, string $role) {
    assertPageAccess($this, $role, $path(), pageAccessExpected($kind, $role));
})->with('page routes')->with(RoleMatrix::ROLES);

it('keeps the staff panel behind the area the route names', function () {
    $mismatched = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'staff'))
        ->filter(function ($route) {
            $kind = pageAccessMatrix()[$route->uri()][1] ?? null;
            $gate = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'can:staff.'));

            return $kind instanceof StaffArea && $gate !== 'can:staff.'.$kind->value;
        })
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($mismatched)->toBe([]);
});

it('lets the person who placed an order, booking or card payment see it', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->pickup()->live()->withLines(1)->forCustomer($customer)->create();
    $booking = Reservation::factory()->pendingDeposit()->create(['user_id' => $customer->id]);

    $this->actingAs($customer);

    $this->get('/orders/'.$order->number)->assertOk();
    $this->get('/book/'.$booking->reference)->assertOk();
    expect($this->get('/orders/'.$order->number.'/pay')->getStatusCode())->not->toBe(403);
    expect($this->get('/book/'.$booking->reference.'/pay')->getStatusCode())->not->toBe(403);
});

it('keeps one customer out of another customer\'s records', function () {
    $other = User::factory()->create();
    $order = Order::factory()->pickup()->live()->withLines(1)->forCustomer($other)->create();
    $booking = Reservation::factory()->create(['user_id' => $other->id]);

    $this->actingAs(User::factory()->create());

    $this->get('/orders/'.$order->number)->assertForbidden();
    $this->get('/orders/'.$order->number.'/pay')->assertForbidden();
    $this->get('/book/'.$booking->reference)->assertForbidden();
    $this->get('/book/'.$booking->reference.'/pay')->assertForbidden();
});

it('answers 404 for records that do not exist, whatever the role', function (string $role, string $path) {
    $actor = RoleMatrix::actor($role);

    if ($actor !== null) {
        $this->actingAs($actor);
    }

    $this->get($path)->assertNotFound();
})->with(['guest', 'customer', 'manager', 'owner'])->with([
    '/menu/not-a-dish',
    '/orders/TH-000000',
    '/book/NOPE0000',
    '/t/not-a-table',
]);

it('answers 404 for missing staff records to the roles allowed in', function (string $path) {
    $this->actingAs(User::factory()->role(Role::Owner)->create());

    $this->get($path)->assertNotFound();
})->with([
    '/staff/orders/TH-000000',
    '/staff/menu/items/999999/edit',
    '/staff/inventory/purchase-orders/999999',
]);

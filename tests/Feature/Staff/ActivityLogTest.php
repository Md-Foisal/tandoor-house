<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo('2026-10-29 15:00:00'));

function actUser(Role $role = Role::Manager): User
{
    return User::factory()->role($role)->create();
}

function actLog(string $event, string $description, array $extra = []): ActivityLog
{
    $log = (new ActivityLog)->forceFill([
        'event' => $event,
        'description' => $description,
        'created_at' => now(),
        ...$extra,
    ]);
    $log->save();

    return $log;
}

function actFeed(array $query = [], Role $role = Role::Manager)
{
    return test()->actingAs(actUser($role))->get('/staff/activity?'.http_build_query($query));
}

it('lets managers and owners in and keeps everyone else out', function (Role $role, int $status) {
    $user = actUser($role);

    $this->actingAs($user)->get('/staff/activity')->assertStatus($status);
    $this->actingAs($user)->get('/staff/activity/export')->assertStatus($status);
})->with([
    'manager' => [Role::Manager, 200],
    'owner' => [Role::Owner, 200],
    'waiter' => [Role::Waiter, 403],
    'cashier' => [Role::Cashier, 403],
    'kitchen' => [Role::Kitchen, 403],
    'rider' => [Role::Rider, 403],
    'customer' => [Role::Customer, 403],
]);

it('lists newest first with day, time and actor', function () {
    $waiter = actUser(Role::Waiter);
    actLog('order.accepted', 'Older', ['created_at' => now()->subDay()->setTime(9, 5)]);
    actLog('order.rejected', 'Newer', ['user_id' => $waiter->id, 'created_at' => now()->setTime(14, 30)]);

    actFeed()->assertInertia(fn (Assert $page) => $page
        ->component('staff/activity/index')
        ->where('today', '2026-10-29')
        ->has('feed.data', 2)
        ->where('feed.data.0.description', 'Newer')
        ->where('feed.data.0.time', '14:30')
        ->where('feed.data.0.day', '2026-10-29')
        ->where('feed.data.0.actor.name', $waiter->name)
        ->where('feed.data.1.day', '2026-10-28'));
});

it('labels entries without a user as automation', function () {
    actLog('automation.cart_reminder', 'Basket reminder emailed');

    actFeed()->assertInertia(fn (Assert $page) => $page
        ->where('feed.data.0.actor', null)
        ->where('people.0.value', 'automation')
        ->where('people.0.label', 'Automation')
        ->where('feed.data.0.family', 'automation'));
});

it('filters by family from the event prefix', function () {
    actLog('order.refunded', 'Refund');
    actLog('stock.low', 'Low');
    actLog('stock_take.done', 'Not stock');

    actFeed(['family' => 'stock'])->assertInertia(fn (Assert $page) => $page
        ->has('feed.data', 1)
        ->where('feed.data.0.event', 'stock.low')
        ->where('families', fn ($families) => collect($families)->pluck('value')->all() === ['order', 'stock', 'stock_take']));
});

it('filters by person and by automation', function () {
    $waiter = actUser(Role::Waiter);
    actLog('order.accepted', 'By waiter', ['user_id' => $waiter->id]);
    actLog('order.auto_accepted', 'By robot');

    actFeed(['person' => (string) $waiter->id])->assertInertia(fn (Assert $page) => $page
        ->has('feed.data', 1)->where('feed.data.0.description', 'By waiter'));
    actFeed(['person' => 'automation'])->assertInertia(fn (Assert $page) => $page
        ->has('feed.data', 1)->where('feed.data.0.description', 'By robot'));
});

it('filters by date range inclusive of both days', function () {
    actLog('order.a', 'Too early', ['created_at' => '2026-10-20 12:00:00']);
    actLog('order.b', 'Start day', ['created_at' => '2026-10-25 00:10:00']);
    actLog('order.c', 'End day', ['created_at' => '2026-10-27 23:50:00']);
    actLog('order.d', 'Too late', ['created_at' => '2026-10-28 00:10:00']);

    actFeed(['from' => '2026-10-25', 'to' => '2026-10-27'])->assertInertia(fn (Assert $page) => $page
        ->has('feed.data', 2)
        ->where('feed.data.0.description', 'End day')
        ->where('feed.data.1.description', 'Start day'));
});

it('searches descriptions and treats wildcards literally', function () {
    actLog('order.a', 'Refund of 50% on order TH-1');
    actLog('order.b', 'Refund of 500 on order TH-2');
    actLog('order.c', 'Something else');

    actFeed(['q' => 'refund'])->assertInertia(fn (Assert $page) => $page->has('feed.data', 2));
    actFeed(['q' => '50%'])->assertInertia(fn (Assert $page) => $page
        ->has('feed.data', 1)->where('feed.data.0.description', 'Refund of 50% on order TH-1'));
});

it('rejects malformed filters', function () {
    actFeed(['from' => 'yesterday'])->assertSessionHasErrors('from');
    actFeed(['family' => 'a b'])->assertSessionHasErrors('family');
    actFeed(['person' => 'x'])->assertSessionHasErrors('person');
});

it('paginates with a cursor, 50 at a time', function () {
    foreach (range(1, 60) as $i) {
        actLog('order.step', "Step {$i}", ['created_at' => now()->subMinutes($i)]);
    }

    $first = actFeed()->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('feed.data', 50)
        ->where('feed.data.0.description', 'Step 1')
        ->where('feed.data.49.description', 'Step 50'));

    $cursor = $first->viewData('page')['scrollProps']['feed']['nextPage'] ?? null;
    expect($cursor)->not->toBeNull();

    $this->actingAs(actUser())
        ->get('/staff/activity?cursor='.$cursor, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $first->viewData('page')['version'],
            'X-Inertia-Partial-Component' => 'staff/activity/index',
            'X-Inertia-Partial-Data' => 'feed',
            'X-Inertia-Infinite-Scroll-Merge-Intent' => 'append',
        ])
        ->assertOk()
        ->assertJsonCount(10, 'props.feed.data')
        ->assertJsonPath('props.feed.data.0.description', 'Step 51');
});

it('links subjects to the staff page that shows them', function () {
    $order = Order::factory()->create();
    $reservation = Reservation::factory()->create();
    $item = MenuItem::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $user = actUser(Role::Waiter);

    actLog('order.accepted', 'o', ['subject_type' => Order::class, 'subject_id' => $order->id]);
    actLog('booking.confirmed', 'r', ['subject_type' => Reservation::class, 'subject_id' => $reservation->id]);
    actLog('menu.sold_out', 'm', ['subject_type' => MenuItem::class, 'subject_id' => $item->id]);
    actLog('stock.low', 'i', ['subject_type' => Ingredient::class, 'subject_id' => $ingredient->id]);
    actLog('team.late', 'u', ['subject_type' => User::class, 'subject_id' => $user->id]);
    actLog('order.gone', 'gone', ['subject_type' => Order::class, 'subject_id' => 999999]);

    $links = actFeed()->inertiaProps('feed.data');
    $bySubject = collect($links)->keyBy('description');

    expect($bySubject['o']['subject'])->toBe(['label' => "Order {$order->number}", 'href' => "/staff/orders/{$order->number}"])
        ->and($bySubject['r']['subject']['href'])->toBe('/staff/reservations?date='.$reservation->starts_at->format('Y-m-d'))
        ->and($bySubject['m']['subject'])->toBe(['label' => $item->name, 'href' => "/staff/menu/items/{$item->id}/edit"])
        ->and($bySubject['i']['subject'])->toBe(['label' => $ingredient->name, 'href' => '/staff/inventory'])
        ->and($bySubject['u']['subject'])->toBeNull()
        ->and($bySubject['gone']['subject'])->toBeNull();
});

it('formats details, with money keys in pounds', function () {
    actLog('order.refunded', 'Refund', ['properties' => [
        'amount' => 1250,
        'refund_pence' => 500,
        'total' => 4000,
        'quantity' => 3,
        'reason' => 'Cold',
        'notified' => true,
        'items' => ['Naan', 'Dal'],
    ]]);

    $details = collect(actFeed()->inertiaProps('feed.data.0.details'))->pluck('value', 'label');

    expect($details->all())->toBe([
        'Amount' => '£12.50',
        'Refund' => '£5',
        'Total' => '£40',
        'Quantity' => '3',
        'Reason' => 'Cold',
        'Notified' => 'Yes',
        'Items' => 'Naan, Dal',
    ]);
});

it('exports the filtered log as csv with en-GB times', function () {
    $waiter = actUser(Role::Waiter);
    $order = Order::factory()->create();
    actLog('order.accepted', 'Accepted it', ['user_id' => $waiter->id, 'subject_type' => Order::class, 'subject_id' => $order->id, 'properties' => ['total' => 2000], 'created_at' => '2026-10-29 14:05:00']);
    actLog('automation.sweep', '=HYPERLINK("x")', ['created_at' => '2026-10-29 13:00:00']);
    actLog('stock.low', 'Filtered out', ['created_at' => '2026-10-20 09:00:00']);

    $response = $this->actingAs(actUser())->get('/staff/activity/export?'.http_build_query(['from' => '2026-10-29']));
    $body = $response->streamedContent();

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('content-disposition'))->toContain('activity-log-2026-10-29.csv')
        ->and($body)->toContain('Time,Who,Area,Event,"What happened",About,Details')
        ->and($body)->toContain('29/10/2026 14:05')
        ->and($body)->toContain($waiter->name)
        ->and($body)->toContain("Order {$order->number}")
        ->and($body)->toContain('Total: £20')
        ->and($body)->toContain('Automation')
        ->and($body)->toContain("'=HYPERLINK")
        ->and($body)->not->toContain('Filtered out');
});

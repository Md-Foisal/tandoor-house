<?php

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\Station;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\User;
use App\Reports\ReportRange;
use App\Reports\ReportSections;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Thursday 29 October 2026, 14:00 in London (the clocks went back on 25 October).
    $this->travelTo(Carbon::parse('2026-10-29 14:00:00', 'Europe/London'));
});

function reportsUser(Role $role = Role::Manager): User
{
    return User::factory()->role($role)->create();
}

/**
 * @param  array<string, mixed>  $order
 * @param  list<array{0: MenuItem, 1: int}>  $lines
 * @param  array<string, int>  $money
 */
function reportOrder(array $order, array $lines, array $money = []): Order
{
    $order = Order::factory()->create([
        'status' => OrderStatus::Completed,
        'payment_method' => PaymentMethod::Card,
        'accepted_at' => null,
        'ready_at' => null,
        'completed_at' => null,
        'estimated_ready_at' => null,
        ...$order,
    ]);

    foreach ($lines as [$item, $quantity]) {
        OrderLine::factory()->for($order)->forItem($item)->create(['quantity' => $quantity]);
    }

    // Lines recalculate the order, so pin the money to known figures afterwards.
    $order->forceFill([
        'discount' => 0, 'delivery_fee' => 0, 'service_charge' => 0, 'tip' => 0,
        ...$money,
    ])->save();
    $order->forceFill(['total' => $order->subtotal - $order->discount + $order->delivery_fee + $order->service_charge + $order->tip])->save();

    return $order;
}

/**
 * Today's service, one yesterday order for the comparison, and orders that must not count.
 *
 * @return array<string, Order|MenuItem>
 */
function reportDataset(): array
{
    $category = Category::factory()->create(['name' => 'Grill']);
    $sides = Category::factory()->create(['name' => 'Sides']);
    $curry = MenuItem::factory()->for($category)->create(['name' => 'Lamb Chops', 'price' => 1200, 'vat_rate' => 2000, 'station' => Station::Tandoor, 'prep_minutes' => 15]);
    $naan = MenuItem::factory()->for($sides)->create(['name' => 'Plain Naan', 'price' => 500, 'vat_rate' => 0, 'station' => Station::Cold, 'prep_minutes' => 5]);
    MenuItem::factory()->for($sides)->create(['name' => 'Slow Dal', 'price' => 700, 'station' => Station::Curry]);
    MenuItem::factory()->for($sides)->soldOut()->create(['name' => 'Off Menu Special']);

    $one = reportOrder([
        'channel' => OrderChannel::Pickup,
        'placed_at' => '2026-10-29 12:30:00',
        'accepted_at' => '2026-10-29 12:31:00',
        'ready_at' => '2026-10-29 12:51:00',
        'estimated_ready_at' => '2026-10-29 12:50:00',
    ], [[$curry, 2], [$naan, 1]], ['vat_total' => 400]);

    $two = reportOrder([
        'channel' => OrderChannel::Delivery,
        'payment_method' => PaymentMethod::ApplePay,
        'placed_at' => '2026-10-29 18:10:00',
        'accepted_at' => '2026-10-29 18:11:00',
        'ready_at' => '2026-10-29 18:41:00',
        'estimated_ready_at' => '2026-10-29 18:50:00',
    ], [[$curry, 1]], ['discount' => 100, 'delivery_fee' => 249, 'tip' => 150, 'vat_total' => 183]);

    $three = reportOrder([
        'channel' => OrderChannel::DineIn,
        'status' => OrderStatus::Served,
        'payment_method' => PaymentMethod::PayAtVenue,
        'placed_at' => '2026-10-29 13:15:00',
        'accepted_at' => '2026-10-29 13:16:00',
        'ready_at' => '2026-10-29 13:26:00',
    ], [[$naan, 2]], ['service_charge' => 120, 'tip' => 100, 'vat_total' => 0]);

    $yesterday = reportOrder([
        'channel' => OrderChannel::Pickup,
        'placed_at' => '2026-10-28 19:00:00',
    ], [[$curry, 1]], ['vat_total' => 200]);

    // Never counted: turned away, cancelled, not yet accepted.
    reportOrder(['status' => OrderStatus::Rejected, 'placed_at' => '2026-10-29 12:00:00'], [[$curry, 5]]);
    reportOrder(['status' => OrderStatus::Cancelled, 'placed_at' => '2026-10-29 12:05:00'], [[$curry, 5]]);
    reportOrder(['status' => OrderStatus::Placed, 'placed_at' => '2026-10-29 13:55:00'], [[$curry, 5]]);

    Payment::factory()->forOrder($one)->refunded(500)->create();
    Payment::factory()->forOrder($two)->create();

    return compact('one', 'two', 'three', 'yesterday', 'curry', 'naan');
}

function reportToday(?OrderChannel $channel = null): ReportRange
{
    return ReportRange::resolve('today', channel: $channel);
}

it('lets managers and owners in and keeps everyone else out', function (Role $role, int $status) {
    $this->actingAs(reportsUser($role));

    $this->get('/staff/reports')->assertStatus($status);
    $this->get('/staff/reports/export/overview')->assertStatus($status);
    $this->get('/staff/reports/pdf')->assertStatus($status);
})->with([
    [Role::Manager, 200], [Role::Owner, 200],
    [Role::Waiter, 403], [Role::Kitchen, 403], [Role::Cashier, 403], [Role::Rider, 403], [Role::Customer, 403],
]);

it('sends guests to sign in', function () {
    $this->get('/staff/reports')->assertRedirect('/login');
});

it('defers every section and tells the page which period it is showing', function () {
    $this->actingAs(reportsUser())
        ->get('/staff/reports?period=yesterday&channel=delivery')
        ->assertInertia(fn ($page) => $page
            ->component('staff/reports/index')
            ->where('filters.period', 'yesterday')
            ->where('filters.channel', 'delivery')
            ->where('range.days', 1)
            ->where('range.label', 'Wed 28 Oct 2026')
            ->where('links.export.vat', url('/staff/reports/export/vat?period=yesterday&channel=delivery'))
            ->missing('overview')
            ->loadDeferredProps(fn ($deferred) => $deferred
                ->where('overview.metrics.0.key', 'sales')
                ->has('breakdown.tables')
                ->has('vat.rows')
                ->has('kitchen.overall')
                ->has('items.tables')
                ->has('tips.days')));
});

it('totals the overview from the orders that count, net of refunds', function () {
    reportDataset();

    $metrics = collect(ReportSections::build('overview', reportToday())['metrics'])->keyBy('key');

    expect($metrics['orders']['value'])->toBe(3)
        ->and($metrics['gross']['value'])->toBe(5100)
        ->and($metrics['discounts']['value'])->toBe(100)
        ->and($metrics['delivery']['value'])->toBe(249)
        ->and($metrics['vat']['value'])->toBe(583)
        ->and($metrics['net_of_vat']['value'])->toBe(4417)
        ->and($metrics['service']['value'])->toBe(120)
        ->and($metrics['tips']['value'])->toBe(250)
        ->and($metrics['refunds']['value'])->toBe(500)
        ->and($metrics['sales']['value'])->toBe(4749)
        ->and($metrics['aov']['value'])->toBe(1750);
});

it('compares with the previous equal period', function () {
    reportDataset();

    $metrics = collect(ReportSections::build('overview', reportToday())['metrics'])->keyBy('key');

    expect($metrics['sales']['previous'])->toBe(1200)
        ->and($metrics['sales']['change'])->toEqualWithDelta(295.8, 0.1)
        ->and($metrics['sales']['direction'])->toBe('up')
        ->and($metrics['discounts']['direction'])->toBe('down')
        ->and($metrics['orders']['previous'])->toBe(1)
        ->and($metrics['orders']['change'])->toBe(200.0)
        ->and($metrics['vat']['previous'])->toBe(200);

    $yesterday = ReportSections::build('overview', ReportRange::resolve('yesterday'));
    $sales = collect($yesterday['metrics'])->firstWhere('key', 'sales');

    expect($sales['value'])->toBe(1200)->and($sales['previous'])->toBe(0)->and($sales['change'])->toBeNull();
});

it('compares a running week with the same days of last week', function () {
    $range = ReportRange::resolve('this_week');

    expect($range->start->toDateString())->toBe('2026-10-26')
        ->and($range->days())->toBe(4)
        ->and($range->previousStart->toDateString())->toBe('2026-10-19')
        ->and($range->previousEnd->toDateString())->toBe('2026-10-23');

    $month = ReportRange::resolve('last_month');

    expect($month->start->toDateString())->toBe('2026-09-01')
        ->and($month->lastDay()->toDateString())->toBe('2026-09-30')
        ->and($month->previousStart->toDateString())->toBe('2026-08-02');
});

it('reports VAT by rate, net and gross', function () {
    reportDataset();

    $vat = ReportSections::build('vat', reportToday());
    $rows = collect($vat['rows'])->keyBy('rate');

    expect($rows[2000])->toBe(['rate' => 2000, 'net' => 2917, 'vat' => 583, 'gross' => 3500])
        ->and($rows[0])->toBe(['rate' => 0, 'net' => 1500, 'vat' => 0, 'gross' => 1500])
        ->and($vat['totals'])->toMatchArray(['net' => 4417, 'vat' => 583, 'gross' => 5000])
        ->and($vat['orders'])->toBe(3);
});

it('splits sales by day, hour, channel and payment method', function () {
    reportDataset();

    $range = ReportRange::resolve('custom', '2026-10-28', '2026-10-29');
    $data = ReportSections::build('breakdown', $range);

    expect($data['granularity'])->toBe('day')
        ->and(array_column($data['daily'], 'sales'))->toBe([1200, 5249 - 0]);

    $hours = collect($data['hours'])->keyBy('hour');

    expect($hours[12]['sales'])->toBe(2900)
        ->and($hours[12]['period'])->toBe('lunch')
        ->and($hours[13]['sales'])->toBe(1000)
        ->and($hours[18]['sales'])->toBe(1349)
        ->and($hours[18]['period'])->toBe('dinner')
        ->and($hours[19]['sales'])->toBe(1200)
        ->and($hours[16]['period'])->toBe('other');

    $channels = collect($data['channels'])->keyBy('key');

    expect($channels['pickup']['sales'])->toBe(4100)
        ->and($channels['delivery']['sales'])->toBe(1349)
        ->and($channels['dine_in']['orders'])->toBe(1)
        ->and(round(array_sum(array_column($data['channels'], 'share'))))->toBe(100.0);

    $payments = collect($data['payments'])->keyBy('key');

    expect($payments['apple_pay']['sales'])->toBe(1349)
        ->and($payments['pay_at_venue']['sales'])->toBe(1000)
        ->and($payments['cash']['sales'])->toBe(0);
});

it('filters every number by channel', function () {
    reportDataset();

    $metrics = collect(ReportSections::build('overview', reportToday(OrderChannel::Delivery))['metrics'])->keyBy('key');

    expect($metrics['orders']['value'])->toBe(1)
        ->and($metrics['tips']['value'])->toBe(150)
        ->and($metrics['refunds']['value'])->toBe(0);
});

it('counts days in London time, not by the clock in UTC', function () {
    $category = Category::factory()->create();
    $item = MenuItem::factory()->for($category)->create(['price' => 1000]);

    reportOrder(['placed_at' => '2026-10-28 23:45:00'], [[$item, 1]]);
    reportOrder(['placed_at' => '2026-10-29 00:10:00'], [[$item, 1]]);

    $yesterday = collect(ReportSections::build('overview', ReportRange::resolve('yesterday'))['metrics'])->firstWhere('key', 'orders');
    $today = collect(ReportSections::build('overview', reportToday())['metrics'])->firstWhere('key', 'orders');

    expect($yesterday['value'])->toBe(1)->and($today['value'])->toBe(1);
});

it('ranks dishes and leaves switched-off dishes out of the slowest list', function () {
    reportDataset();

    $items = ReportSections::build('items', reportToday());

    expect($items['top_quantity'][0])->toMatchArray(['name' => 'Lamb Chops', 'quantity' => 3, 'revenue' => 3600])
        ->and($items['top_quantity'][1])->toMatchArray(['name' => 'Plain Naan', 'quantity' => 3, 'revenue' => 1500])
        ->and($items['top_revenue'][0]['name'])->toBe('Lamb Chops')
        ->and($items['slow_quantity'][0])->toMatchArray(['name' => 'Slow Dal', 'quantity' => 0])
        ->and(array_column($items['slow_quantity'], 'name'))->not->toContain('Off Menu Special')
        ->and(collect($items['categories'])->firstWhere('name', 'Grill')['revenue'])->toBe(3600);
});

it('measures prep time overall and by station, and flags late orders', function () {
    reportDataset();

    $kitchen = ReportSections::build('kitchen', reportToday());

    // 20, 30 and 10 minutes. The first was ready after its estimate; the
    // third had no estimate and took longer than its slowest dish's 5 minutes.
    expect($kitchen['overall'])->toMatchArray(['orders' => 3, 'avg_minutes' => 20.0, 'late' => 2, 'late_percent' => 66.7]);

    $stations = collect($kitchen['stations'])->keyBy('key');

    expect($stations['tandoor'])->toMatchArray(['orders' => 2, 'avg_minutes' => 25.0, 'late' => 1, 'late_percent' => 50.0])
        ->and($stations['cold'])->toMatchArray(['orders' => 2, 'avg_minutes' => 15.0, 'late' => 2])
        ->and($stations)->not->toHaveKey('bar');
});

it('totals service charge and tips by day', function () {
    reportDataset();

    $tips = ReportSections::build('tips', ReportRange::resolve('custom', '2026-10-28', '2026-10-29'));

    expect($tips['days'][0])->toMatchArray(['service' => 0, 'tips' => 0])
        ->and($tips['days'][1])->toMatchArray(['service' => 120, 'tips' => 250, 'total' => 370])
        ->and($tips['totals'])->toMatchArray(['service' => 120, 'tips' => 250, 'total' => 370]);
});

it('groups long ranges by week', function () {
    $range = ReportRange::resolve('custom', '2026-01-01', '2026-06-30');
    $data = ReportSections::build('breakdown', $range);

    expect($data['granularity'])->toBe('week')
        ->and(count($data['daily']))->toBeLessThan(30)
        ->and($data['daily'][0]['key'])->toBe('2025-12-29');
});

it('streams a CSV with a byte order mark, en-GB dates and pounds', function () {
    reportDataset();

    $response = $this->actingAs(reportsUser())
        ->get('/staff/reports/export/breakdown?period=custom&from=2026-10-28&to=2026-10-29')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('tandoor-house-breakdown-2026-10-28_2026-10-29.csv');

    $csv = $response->streamedContent();

    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($csv)->toContain('Tandoor House')
        ->and($csv)->toContain('Sales by day')
        ->and($csv)->toContain('Day,Orders,Sales')
        ->and($csv)->toContain('28/10/2026,1,12.00')
        ->and($csv)->toContain('29/10/2026,3,52.49')
        ->and($csv)->toContain('Total,4,64.49')
        ->and($csv)->toContain('Card')
        ->and($csv)->toContain('"Pay at venue",1,10.00');
});

it('exports the overview with signed changes and the VAT report by rate', function () {
    reportDataset();

    $overview = $this->actingAs(reportsUser())->get('/staff/reports/export/overview?period=today')->streamedContent();

    expect($overview)->toContain('Measure,"This period","Previous period",Change')
        ->and($overview)->toContain('"Sales after refunds",47.49,12.00,+295.8%')
        ->and($overview)->toContain('Orders,3,1,+200.0%');

    $vat = $this->actingAs(reportsUser())->get('/staff/reports/export/vat?period=today')->streamedContent();

    expect($vat)->toContain('"VAT rate",Net,VAT,Gross')
        ->and($vat)->toContain('20%,29.17,5.83,35.00')
        ->and($vat)->toContain('0%,15.00,0.00,15.00')
        ->and($vat)->toContain('Total,44.17,5.83,50.00');
});

it('does not let a dish name run as a spreadsheet formula', function () {
    $category = Category::factory()->create();
    $item = MenuItem::factory()->for($category)->create(['name' => '=HYPERLINK("x")', 'price' => 500]);
    reportOrder(['placed_at' => '2026-10-29 12:00:00'], [[$item, 1]]);

    $csv = $this->actingAs(reportsUser())->get('/staff/reports/export/items?period=today')->streamedContent();

    expect($csv)->toContain("'=HYPERLINK");
});

it('downloads the whole report as a PDF', function () {
    reportDataset();

    $response = $this->actingAs(reportsUser(Role::Owner))
        ->get('/staff/reports/pdf?period=today&channel=pickup')
        ->assertOk()
        ->assertDownload('tandoor-house-report-2026-10-29_2026-10-29.pdf');

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('refuses a range that ends before it starts', function () {
    $this->actingAs(reportsUser())
        ->get('/staff/reports?period=custom&from=2026-10-20&to=2026-10-10')
        ->assertRedirect('/staff/reports')
        ->assertSessionHasErrors(['to' => "The end date can't be before the start date."]);
});

it('refuses ranges longer than 366 days but accepts exactly 366', function () {
    $this->actingAs(reportsUser());

    $this->get('/staff/reports?period=custom&from=2025-01-01&to=2026-01-02')->assertSessionHasErrors('to');
    $this->get('/staff/reports?period=custom&from=2025-01-01&to=2026-01-01')->assertSessionHasNoErrors()->assertOk();
    $this->get('/staff/reports/export/vat?period=custom&from=2024-01-01&to=2026-01-01')->assertSessionHasErrors('to');
    $this->get('/staff/reports/pdf?period=custom&from=2024-01-01&to=2026-01-01')->assertSessionHasErrors('to');
});

it('refuses a custom range with missing or malformed dates, and unknown periods or channels', function (string $query, string $field) {
    $this->actingAs(reportsUser())->get('/staff/reports?'.$query)->assertSessionHasErrors($field);
})->with([
    'no dates' => ['period=custom', 'from'],
    'bad date' => ['period=custom&from=yesterday&to=2026-10-02', 'from'],
    'unknown period' => ['period=fortnight', 'period'],
    'unknown channel' => ['channel=drone', 'channel'],
]);

it('gives an unknown export section a 404', function () {
    $this->actingAs(reportsUser())->get('/staff/reports/export/payroll')->assertNotFound();
});

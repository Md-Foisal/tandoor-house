<?php

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\Station;
use App\Mail\DailyCloseReport;
use App\Mail\WeeklyReport;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\User;
use App\Reports\ReportRange;
use App\Reports\SalesQuery;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Thursday 29 October 2026, 23:30 in London.
    $this->travelTo(Carbon::parse('2026-10-29 23:30:00', 'Europe/London'));
    Mail::fake();
});

function switchReports(bool $daily, bool $weekly): void
{
    app(Settings::class)->set('automations', ['daily_close' => $daily, 'weekly_report' => $weekly]);
}

/**
 * @param  array<string, mixed>  $order
 * @param  list<array{0: MenuItem, 1: int}>  $lines
 * @param  array<string, int>  $money
 */
function digestOrder(array $order, array $lines, array $money = []): Order
{
    $order = Order::factory()->create([
        'status' => OrderStatus::Completed,
        'payment_method' => PaymentMethod::Card,
        'accepted_at' => null,
        'ready_at' => null,
        'estimated_ready_at' => null,
        ...$order,
    ]);

    foreach ($lines as [$item, $quantity]) {
        OrderLine::factory()->for($order)->forItem($item)->create(['quantity' => $quantity]);
    }

    $order->forceFill(['discount' => 0, 'delivery_fee' => 0, 'service_charge' => 0, 'tip' => 0, ...$money])->save();
    $order->forceFill(['total' => $order->subtotal - $order->discount + $order->delivery_fee + $order->service_charge + $order->tip])->save();

    return $order;
}

/**
 * Thursday's service, a Monday-to-Sunday week before it, and orders that never count.
 *
 * @return array<string, MenuItem>
 */
function digestData(): array
{
    $grill = Category::factory()->create(['name' => 'Grill']);
    $chops = MenuItem::factory()->for($grill)->create(['name' => 'Lamb Chops', 'price' => 1200, 'vat_rate' => 2000, 'station' => Station::Tandoor, 'prep_minutes' => 15]);
    $naan = MenuItem::factory()->for($grill)->create(['name' => 'Plain Naan', 'price' => 500, 'vat_rate' => 0, 'station' => Station::Cold, 'prep_minutes' => 5]);
    MenuItem::factory()->for($grill)->create(['name' => 'Slow Dal', 'price' => 700, 'station' => Station::Curry]);

    digestOrder([
        'channel' => OrderChannel::Pickup, 'placed_at' => '2026-10-29 12:30:00',
        'accepted_at' => '2026-10-29 12:31:00', 'ready_at' => '2026-10-29 12:51:00',
    ], [[$chops, 2], [$naan, 1]], ['vat_total' => 400]);

    digestOrder([
        'channel' => OrderChannel::Delivery, 'payment_method' => PaymentMethod::ApplePay, 'placed_at' => '2026-10-29 18:10:00',
        'accepted_at' => '2026-10-29 18:11:00', 'ready_at' => '2026-10-29 18:41:00',
    ], [[$chops, 1]], ['discount' => 100, 'delivery_fee' => 249, 'tip' => 150, 'vat_total' => 183]);

    digestOrder([
        'channel' => OrderChannel::DineIn, 'status' => OrderStatus::Served, 'payment_method' => PaymentMethod::PayAtVenue,
        'placed_at' => '2026-10-29 13:15:00',
    ], [[$naan, 2]], ['service_charge' => 120, 'tip' => 100]);

    // The week before, and the day before.
    digestOrder(['placed_at' => '2026-10-22 19:00:00'], [[$chops, 1]], ['vat_total' => 200]);
    digestOrder(['placed_at' => '2026-10-28 19:00:00'], [[$naan, 1]]);

    digestOrder(['status' => OrderStatus::Rejected, 'placed_at' => '2026-10-29 12:00:00'], [[$chops, 5]]);
    digestOrder(['status' => OrderStatus::Cancelled, 'placed_at' => '2026-10-29 12:05:00'], [[$chops, 5]]);

    Reservation::factory()->create(['starts_at' => '2026-10-29 19:00:00', 'ends_at' => '2026-10-29 21:00:00', 'party_size' => 4, 'status' => 'confirmed']);

    $salt = Ingredient::factory()->create(['name' => 'Basmati', 'unit' => 'g', 'stock' => 100, 'low_threshold' => 500, 'cost_per_thousand' => 200]);
    StockMovement::create(['ingredient_id' => $salt->id, 'type' => 'waste', 'quantity' => -2000, 'reason' => 'Spoiled']);

    return compact('chops', 'naan');
}

function owner(): User
{
    return User::factory()->role(Role::Owner)->create(['name' => 'Priya Nair']);
}

it('does nothing when the daily close switch is off', function () {
    owner();
    digestData();
    switchReports(false, false);

    $this->artisan('reports:daily-close')->assertSuccessful();

    Mail::assertNothingQueued();
    expect(ActivityLog::where('event', 'automation.report.daily_close')->count())->toBe(0);
});

it('emails owners only, with the same numbers as the Reports screen', function () {
    $owner = owner();
    User::factory()->role(Role::Manager)->create();
    User::factory()->role(Role::Customer)->create();
    $data = digestData();
    switchReports(true, false);

    $this->artisan('reports:daily-close')->assertSuccessful();

    Mail::assertQueued(DailyCloseReport::class, 1);
    Mail::assertQueued(DailyCloseReport::class, fn (DailyCloseReport $mail) => $mail->hasTo($owner->email));

    $totals = SalesQuery::totals(ReportRange::resolve('today'));
    expect($totals['orders'])->toBe(3);

    Mail::queued(DailyCloseReport::class)->first()->assertSeeInHtml(Money::format($totals['sales']), false);

    $html = Mail::queued(DailyCloseReport::class)->first()->render();

    expect($html)
        ->toContain('Hello Priya,')
        ->toContain(Money::format($totals['sales']))
        ->toContain(Money::format($totals['net_of_vat']))
        ->toContain(Money::format($totals['aov']))
        ->toContain(Money::format($totals['tips']))
        ->toContain(Money::format($totals['service']))
        ->toContain(Money::format($totals['vat']))
        ->toContain($data['chops']->name)
        ->toContain('Pickup')
        ->toContain('Basmati')
        ->toContain('4 covers')
        ->toContain('/staff/reports?period=custom&amp;from=2026-10-29&amp;to=2026-10-29');

    expect(ActivityLog::where('event', 'automation.report.daily_close')->count())->toBe(1);
});

it('counts rejected and cancelled orders and the waste cost', function () {
    owner();
    digestData();
    switchReports(true, false);

    $this->artisan('reports:daily-close');

    $html = Mail::queued(DailyCloseReport::class)->first()->render();

    // Two turned away; 2,000 g at 200p per 1,000 g is £4.
    expect($html)->toContain('Rejected or cancelled orders')->toContain('Waste written off: £4 across 1 entry');
});

it('sends the close once per day and again only when forced', function () {
    owner();
    digestData();
    switchReports(true, false);

    $this->artisan('reports:daily-close');
    $this->artisan('reports:daily-close');

    Mail::assertQueued(DailyCloseReport::class, 1);

    $this->artisan('reports:daily-close', ['--force' => true]);

    Mail::assertQueued(DailyCloseReport::class, 2);
});

it('handles a quiet day without orders', function () {
    owner();
    switchReports(true, false);

    $this->artisan('reports:daily-close')->assertSuccessful();

    expect(Mail::queued(DailyCloseReport::class)->first()->render())->toContain('No orders were completed');
});

it('does nothing when the weekly switch is off', function () {
    owner();
    switchReports(true, false);

    $this->artisan('reports:weekly')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('sends last week with a comparison and the spreadsheets attached', function () {
    $this->travelTo(Carbon::parse('2026-11-02 07:00:00', 'Europe/London'));
    owner();
    digestData();
    switchReports(false, true);

    $this->artisan('reports:weekly')->assertSuccessful();

    Mail::assertQueued(WeeklyReport::class, 1);

    $mail = Mail::queued(WeeklyReport::class)->first();
    $week = ReportRange::resolve('custom', '2026-10-26', '2026-11-01');
    $totals = SalesQuery::totals($week);
    $html = $mail->render();

    expect($totals['orders'])->toBe(4)
        ->and($html)->toContain(Money::format($totals['sales']))
        ->toContain('Mon 26 Oct to Sun 1 Nov 2026')
        ->toContain('Busiest day')
        ->toContain('Busiest hour')
        ->toContain('Slow Dal')
        ->toContain('up ')
        ->and($mail->attachments())->toHaveCount(6)
        ->and(array_keys($mail->csv)[0])->toBe('tandoor-house-overview-2026-10-26_2026-11-01.csv');

    expect($mail->csv['tandoor-house-overview-2026-10-26_2026-11-01.csv'])->toContain((string) number_format($totals['sales'] / 100, 2, '.', ''));
});

it('sends the weekly report once per week', function () {
    $this->travelTo(Carbon::parse('2026-11-02 07:00:00', 'Europe/London'));
    owner();
    switchReports(false, true);

    $this->artisan('reports:weekly');
    $this->artisan('reports:weekly');

    Mail::assertQueued(WeeklyReport::class, 1);
    expect(ActivityLog::where('event', 'automation.report.weekly')->count())->toBe(1);
});

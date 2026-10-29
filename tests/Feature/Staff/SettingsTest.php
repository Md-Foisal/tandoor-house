<?php

use App\Enums\Role;
use App\Http\Controllers\Staff\Settings\BankHolidayController;
use App\Http\Controllers\Staff\Settings\SettingsController;
use App\Models\ActivityLog;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\OpeningHours;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

function settingsUser(Role $role): User
{
    return User::factory()->role($role)->create();
}

function weekWith(array $overrides = []): array
{
    $days = [];

    foreach (range(1, 7) as $day) {
        $days[] = $overrides[$day] ?? ['day' => $day, 'closed' => true, 'periods' => []];
    }

    return ['days' => $days];
}

function openDay(int $day, array $periods): array
{
    return ['day' => $day, 'closed' => false, 'periods' => array_map(fn ($p) => ['open' => $p[0], 'close' => $p[1]], $periods)];
}

function zonePayload(array $over = []): array
{
    return ['name' => 'Whitechapel', 'prefixes' => 'E1, E2', 'fee' => 250, 'minimum_order' => 1500, 'eta_minutes' => 30, 'is_active' => true, ...$over];
}

function taxPayload(array $over = []): array
{
    return ['vat_rate' => 2000, 'service_charge_rate' => 1250, 'tip_presets' => [0, 1000, 1500], 'deposit_per_head' => 1000, 'deposit_from_party' => 6, ...$over];
}

it('lets owners and managers in and nobody else', function (Role $role, int $status) {
    $this->actingAs(settingsUser($role))->get('/staff/settings')->assertStatus($status);
})->with([
    [Role::Owner, 200], [Role::Manager, 200],
    [Role::Waiter, 403], [Role::Cashier, 403], [Role::Kitchen, 403], [Role::Rider, 403], [Role::Customer, 403],
]);

it('keeps every write behind the settings area', function () {
    $this->actingAs(settingsUser(Role::Waiter));
    $zone = DeliveryZone::factory()->create();

    $this->put('/staff/settings/hours', weekWith())->assertForbidden();
    $this->put('/staff/settings/holidays', ['dates' => []])->assertForbidden();
    $this->post('/staff/settings/zones', zonePayload())->assertForbidden();
    $this->delete("/staff/settings/zones/{$zone->id}")->assertForbidden();
    $this->put('/staff/settings/tax', taxPayload())->assertForbidden();
    $this->put('/staff/settings/automations', [])->assertForbidden();
});

it('shares the sections with the page and flags who can edit money', function () {
    $this->actingAs(settingsUser(Role::Manager))->get('/staff/settings?section=tax')->assertInertia(fn ($page) => $page
        ->component('staff/settings/index')
        ->where('section', 'tax')
        ->where('can_edit_money', false)
        ->where('tax.vat_rate', 2000)
        ->has('automations', 10)
        ->has('hours', 7));

    $this->actingAs(settingsUser(Role::Owner))->get('/staff/settings')->assertInertia(fn ($page) => $page
        ->where('section', null)->where('can_edit_money', true));
});

it('saves opening hours that OpeningHours reads back', function () {
    $this->travelTo('2026-10-28 13:00:00'); // Wednesday

    $this->actingAs(settingsUser(Role::Manager))
        ->put('/staff/settings/hours', weekWith([3 => openDay(3, [['09:00', '11:00'], ['14:00', '00:00']])]))
        ->assertRedirect();

    expect(app(Settings::class)->get('opening_hours')[3])->toBe([['09:00', '11:00'], ['14:00', '24:00']]);

    $hours = app(OpeningHours::class);
    expect($hours->status())->toMatchArray(['isOpen' => false])
        ->and($hours->isOpen(now()->setTime(23, 30)))->toBeTrue()
        ->and($hours->isOpen(now()->setTime(10, 0)))->toBeTrue()
        ->and($hours->isOpen(now()->setTime(12, 0)))->toBeFalse()
        ->and($hours->status(now()->setTime(15, 0)))->toMatchArray(['isOpen' => true]);

    $log = ActivityLog::where('event', 'settings.hours')->sole();
    expect($log->properties)->toHaveKeys(['before', 'after']);
});

it('rejects bad opening hours with inline errors', function (array $periods, string $key) {
    $this->actingAs(settingsUser(Role::Owner))
        ->put('/staff/settings/hours', weekWith([2 => openDay(2, $periods)]))
        ->assertSessionHasErrors($key);
})->with([
    'close before open' => [[['18:00', '17:00']], 'days.1.periods.0.close'],
    'equal times' => [[['18:00', '18:00']], 'days.1.periods.0.close'],
    'overlap' => [[['12:00', '15:00'], ['14:00', '16:00']], 'days.1.periods.1.open'],
    'too many' => [[['08:00', '09:00'], ['10:00', '11:00'], ['12:00', '13:00'], ['14:00', '15:00']], 'days.1.periods'],
    'bad time' => [[['25:00', '26:00']], 'days.1.periods.0.open'],
]);

it('needs a period unless the day is closed', function () {
    $this->actingAs(settingsUser(Role::Owner))
        ->put('/staff/settings/hours', weekWith([2 => openDay(2, [])]))
        ->assertSessionHasErrors('days.1.periods');
});

it('adds, removes and merges bank holidays', function () {
    $this->actingAs(settingsUser(Role::Manager));

    $this->put('/staff/settings/holidays', ['dates' => ['2026-12-25', '2026-12-26']])->assertRedirect();
    expect(app(Settings::class)->get('bank_holidays'))->toBe(['2026-12-25', '2026-12-26']);

    $this->put('/staff/settings/holidays', ['dates' => ['2026-12-26']]);
    expect(app(Settings::class)->get('bank_holidays'))->toBe(['2026-12-26']);

    $this->put('/staff/settings/holidays', ['dates' => ['26/12/2026']])->assertSessionHasErrors('dates.0');

    $this->post('/staff/settings/holidays/next-year')->assertRedirect();
    $dates = app(Settings::class)->get('bank_holidays');
    expect($dates)->toContain('2026-12-26', '2027-12-27')->and(count($dates))->toBe(1 + count(BankHolidayController::NEXT_YEAR_DATES));

    expect(ActivityLog::where('event', 'settings.holidays')->count())->toBe(3);
});

it('closes the restaurant on a saved holiday', function () {
    $this->travelTo('2026-11-03 19:00:00'); // Tuesday, normally open
    expect(app(OpeningHours::class)->isOpen())->toBeTrue();

    $this->actingAs(settingsUser(Role::Owner))->put('/staff/settings/holidays', ['dates' => ['2026-11-03']]);

    expect(app(OpeningHours::class)->isOpen())->toBeFalse();
});

it('validates delivery zones and matches exact outward codes', function (array $over, string $key) {
    $this->actingAs(settingsUser(Role::Owner))
        ->post('/staff/settings/zones', zonePayload($over))
        ->assertSessionHasErrors($key);
})->with([
    'no name' => [['name' => ''], 'name'],
    'full postcode' => [['prefixes' => 'E1 6QR'], 'prefixes.1'],
    'not a code' => [['prefixes' => 'ZZZZ'], 'prefixes.0'],
    'negative fee' => [['fee' => -1], 'fee'],
    'eta too low' => [['eta_minutes' => 1], 'eta_minutes'],
]);

it('lets the owner add a zone, normalising the codes', function () {
    $this->actingAs(settingsUser(Role::Owner))
        ->post('/staff/settings/zones', zonePayload(['prefixes' => 'e1, E14  n1']))
        ->assertRedirect();

    $zone = DeliveryZone::sole();
    expect($zone->postcode_prefixes)->toBe(['E1', 'E14', 'N1'])->and($zone->fee)->toBe(250);
    expect(ActivityLog::where('event', 'settings.zones')->count())->toBe(1);
});

it('rejects a code another active zone already covers', function () {
    DeliveryZone::factory()->covering(['E1'])->create(['name' => 'Inner']);

    $this->actingAs(settingsUser(Role::Owner))
        ->post('/staff/settings/zones', zonePayload(['prefixes' => 'E1']))
        ->assertSessionHasErrors('prefixes');
});

it('lets managers edit a zone but never its fee or minimum, or add one', function () {
    $zone = DeliveryZone::factory()->create(['fee' => 249, 'minimum_order' => 1500, 'postcode_prefixes' => ['E3']]);
    $this->actingAs(settingsUser(Role::Manager));

    $this->post('/staff/settings/zones', zonePayload())->assertForbidden();
    $this->patch("/staff/settings/zones/{$zone->id}", zonePayload(['prefixes' => 'E3', 'fee' => 100]))->assertForbidden();
    $this->patch("/staff/settings/zones/{$zone->id}", zonePayload(['prefixes' => 'E3', 'fee' => 249, 'minimum_order' => 900]))->assertForbidden();

    $this->patch("/staff/settings/zones/{$zone->id}", zonePayload(['name' => 'Bow', 'prefixes' => 'E3', 'fee' => 249, 'eta_minutes' => 45, 'is_active' => false]))
        ->assertRedirect();
    expect($zone->fresh())->name->toBe('Bow')->eta_minutes->toBe(45)->is_active->toBeFalse()->fee->toBe(249);
});

it('deletes an unused zone and deactivates one orders reference', function () {
    $unused = DeliveryZone::factory()->create();
    $used = DeliveryZone::factory()->create();
    Order::factory()->create(['delivery_zone_id' => $used->id]);
    $this->actingAs(settingsUser(Role::Owner));

    $this->delete("/staff/settings/zones/{$unused->id}")->assertRedirect();
    $this->delete("/staff/settings/zones/{$used->id}")->assertRedirect();

    expect(DeliveryZone::find($unused->id))->toBeNull()
        ->and($used->fresh()->is_active)->toBeFalse();
});

it('saves tax and charges for the owner and clears the menu cache', function () {
    Cache::put('menu.v1', 'cached');
    $this->actingAs(settingsUser(Role::Owner))
        ->put('/staff/settings/tax', taxPayload(['vat_rate' => 500, 'tip_presets' => [1500, 0, 1000], 'deposit_per_head' => 1500]))
        ->assertRedirect();

    $settings = app(Settings::class);
    expect($settings->get('vat_rate'))->toBe(500)
        ->and($settings->get('tip_presets'))->toBe([0, 1000, 1500])
        ->and($settings->get('reservation.deposit_per_head'))->toBe(1500)
        ->and(Cache::has('menu.v1'))->toBeFalse();

    $log = ActivityLog::where('event', 'settings.tax')->sole();
    expect($log->properties['before']['vat_rate'])->toBe(2000)->and($log->properties['after']['vat_rate'])->toBe(500);
});

it('keeps tax and charges away from managers', function () {
    $this->actingAs(settingsUser(Role::Manager))->put('/staff/settings/tax', taxPayload(['vat_rate' => 0]))->assertForbidden();

    expect(Setting::count())->toBe(0);
});

it('validates tax and charges', function (array $over, string $key) {
    $this->actingAs(settingsUser(Role::Owner))->put('/staff/settings/tax', taxPayload($over))->assertSessionHasErrors($key);
})->with([
    'vat too high' => [['vat_rate' => 9000], 'vat_rate'],
    'duplicate tips' => [['tip_presets' => [1000, 1000]], 'tip_presets.1'],
    'no tips' => [['tip_presets' => []], 'tip_presets'],
    'party of one' => [['deposit_from_party' => 1], 'deposit_from_party'],
]);

it('saves the receipt footer, notifications and hygiene details', function () {
    $this->actingAs(settingsUser(Role::Manager));

    $this->put('/staff/settings/receipts', ['footer' => 'Thank you!'])->assertRedirect();
    $this->put('/staff/settings/notifications', ['mail' => true, 'sms' => false, 'owner_email' => 'boss@example.com'])->assertRedirect();
    $this->put('/staff/settings/hygiene', ['rating' => 'Awaiting inspection', 'opened_year' => 2018])->assertRedirect();

    $settings = app(Settings::class);
    expect($settings->get('receipt_footer'))->toBe('Thank you!')
        ->and($settings->get('notification_channels'))->toBe(['mail' => true, 'sms' => false, 'whatsapp' => false])
        ->and($settings->get('owner_email'))->toBe('boss@example.com')
        ->and($settings->get('hygiene_rating'))->toBe('Awaiting inspection')
        ->and($settings->get('opened_year'))->toBe(2018);

    $this->put('/staff/settings/hygiene', ['rating' => 4, 'opened_year' => 2018]);
    expect($settings->get('hygiene_rating'))->toBe(4);
});

it('will not switch WhatsApp on or accept a bad rating or email', function () {
    $this->actingAs(settingsUser(Role::Owner));

    $this->put('/staff/settings/notifications', ['mail' => true, 'sms' => true, 'whatsapp' => true])->assertSessionHasErrors('whatsapp');
    $this->put('/staff/settings/notifications', ['mail' => true, 'sms' => true, 'owner_email' => 'nope'])->assertSessionHasErrors('owner_email');
    $this->put('/staff/settings/hygiene', ['rating' => 7, 'opened_year' => 2018])->assertSessionHasErrors('rating');
    $this->put('/staff/settings/hygiene', ['rating' => 5, 'opened_year' => now()->year + 1])->assertSessionHasErrors('opened_year');
});

it('toggles automations and logs which ones changed', function () {
    $all = array_fill_keys(SettingsController::AUTOMATIONS, true);
    $this->actingAs(settingsUser(Role::Manager));

    expect(app(Settings::class)->enabled('loyalty'))->toBeTrue();

    $this->put('/staff/settings/automations', [...$all, 'loyalty' => false, 'auto_accept' => false])->assertRedirect();

    $settings = app(Settings::class);
    expect($settings->enabled('loyalty'))->toBeFalse()
        ->and($settings->enabled('auto_accept'))->toBeFalse()
        ->and($settings->enabled('daily_close'))->toBeTrue();

    $log = ActivityLog::where('event', 'settings.automations')->sole();
    expect($log->description)->toContain('auto_accept')->toContain('loyalty')->not->toContain('daily_close')
        ->and($log->properties['after']['automations']['loyalty'])->toBeFalse();

    $this->put('/staff/settings/automations', [...$all, 'loyalty' => false, 'auto_accept' => false]);
    expect(ActivityLog::where('event', 'settings.automations')->count())->toBe(1);
});

it('requires every automation key', function () {
    $this->actingAs(settingsUser(Role::Owner))->put('/staff/settings/automations', ['loyalty' => true])->assertSessionHasErrors('auto_accept');
});

it('flushes the settings cache after every save', function () {
    $settings = app(Settings::class);
    $settings->get('vat_rate');
    expect(Cache::has('settings.all'))->toBeTrue();

    $this->actingAs(settingsUser(Role::Owner))->put('/staff/settings/receipts', ['footer' => 'Hello']);

    expect(Cache::has('settings.all'))->toBeFalse()
        ->and($settings->get('receipt_footer'))->toBe('Hello');
});

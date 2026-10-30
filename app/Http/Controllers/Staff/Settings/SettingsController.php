<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Services\OpeningHours;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public const SECTIONS = ['hours', 'holidays', 'zones', 'tax', 'receipts', 'notifications', 'hygiene', 'automations'];

    public const AUTOMATIONS = [
        'auto_accept', 'notifications', 'stock_deduction', 'reservation_reminders', 'no_show_release',
        'daily_close', 'weekly_report', 'review_requests', 'loyalty', 'abandoned_cart',
        'prep_estimates',
    ];

    public function index(Request $request, Settings $settings, OpeningHours $hours): Response
    {
        $section = $request->query('section');
        $section = in_array($section, self::SECTIONS, true) ? $section : null;

        $orderCounts = Order::query()->whereNotNull('delivery_zone_id')->groupBy('delivery_zone_id')
            ->selectRaw('delivery_zone_id, count(*) as total')->pluck('total', 'delivery_zone_id');

        return Inertia::render('staff/settings/index', [
            'section' => $section,
            'can_edit_money' => $request->user()?->role === Role::Owner,
            'hours' => $this->hours($settings),
            'open_now' => $hours->status(),
            'holidays' => array_values((array) $settings->get('bank_holidays', [])),
            'next_year' => ['year' => BankHolidayController::NEXT_YEAR, 'dates' => BankHolidayController::NEXT_YEAR_DATES],
            'zones' => DeliveryZone::query()->orderBy('name')->get()->map(fn (DeliveryZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'prefixes' => $zone->postcode_prefixes,
                'fee' => $zone->fee,
                'minimum_order' => $zone->minimum_order,
                'eta_minutes' => $zone->eta_minutes,
                'is_active' => $zone->is_active,
                'orders_count' => (int) ($orderCounts[$zone->id] ?? 0),
            ])->all(),
            'tax' => [
                'vat_rate' => (int) $settings->get('vat_rate'),
                'service_charge_rate' => (int) $settings->get('service_charge_rate'),
                'tip_presets' => array_values(array_map('intval', (array) $settings->get('tip_presets'))),
                'deposit_per_head' => (int) $settings->get('reservation.deposit_per_head'),
                'deposit_from_party' => (int) $settings->get('reservation.deposit_from_party'),
            ],
            'receipts' => [
                'footer' => (string) $settings->get('receipt_footer', ''),
                'vat_number' => config('restaurant.company.vat_number'),
                'business' => [
                    'name' => config('restaurant.name'),
                    'legal_name' => config('restaurant.company.legal_name'),
                    'address' => config('restaurant.contact.address'),
                    'phone' => config('restaurant.contact.phone'),
                ],
            ],
            'notifications' => [
                'channels' => $this->channels($settings),
                'owner_email' => (string) $settings->get('owner_email', ''),
            ],
            'hygiene' => [
                'rating' => $settings->get('hygiene_rating'),
                'opened_year' => (int) $settings->get('opened_year'),
                'year_now' => now()->year,
            ],
            'automations' => self::automations($settings),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    public static function automations(Settings $settings): array
    {
        $stored = (array) $settings->get('automations', []);

        return collect(self::AUTOMATIONS)
            ->mapWithKeys(fn (string $key) => [$key => (bool) ($stored[$key] ?? config("restaurant.defaults.automations.{$key}", false))])
            ->all();
    }

    /**
     * @return array{mail: bool, sms: bool, whatsapp: bool}
     */
    public static function channels(Settings $settings): array
    {
        $stored = (array) $settings->get('notification_channels', []);

        return [
            'mail' => (bool) ($stored['mail'] ?? true),
            'sms' => (bool) ($stored['sms'] ?? true),
            'whatsapp' => false,
        ];
    }

    /**
     * @return list<array{day: int, closed: bool, periods: list<array{open: string, close: string}>}>
     */
    private function hours(Settings $settings): array
    {
        $week = (array) $settings->get('opening_hours', []);

        return array_map(function (int $day) use ($week) {
            $periods = $week[$day] ?? $week[(string) $day] ?? [];

            return [
                'day' => $day,
                'closed' => $periods === [],
                'periods' => array_map(fn (array $p) => [
                    'open' => $p[0],
                    'close' => $p[1] === '24:00' ? '00:00' : $p[1],
                ], array_values($periods)),
            ];
        }, range(1, 7));
    }
}

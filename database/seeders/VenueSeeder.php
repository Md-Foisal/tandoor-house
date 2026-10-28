<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The room, the delivery map and the owner's settings.
 */
class VenueSeeder extends Seeder
{
    public function run(): void
    {
        $this->tables();
        $this->zones();
        $this->settings();
    }

    private function tables(): void
    {
        // Label, seats, area, x, y, width, height, shape on a 24 x 16 grid.
        $plan = [
            ['W1', 2, 'Window', 0, 0, 2, 2, 'square'],
            ['W2', 2, 'Window', 3, 0, 2, 2, 'square'],
            ['W3', 2, 'Window', 6, 0, 2, 2, 'square'],
            ['M1', 4, 'Main room', 0, 4, 3, 2, 'rect'],
            ['M2', 4, 'Main room', 4, 4, 3, 2, 'rect'],
            ['M3', 4, 'Main room', 8, 4, 2, 2, 'round'],
            ['M4', 4, 'Main room', 11, 4, 2, 2, 'round'],
            ['M5', 4, 'Main room', 0, 8, 3, 2, 'rect'],
            ['M6', 4, 'Main room', 4, 8, 3, 2, 'rect'],
            ['M7', 6, 'Main room', 8, 8, 3, 3, 'round'],
            ['M8', 8, 'Main room', 0, 12, 6, 2, 'rect'],
            ['C1', 2, 'Tandoor counter', 16, 0, 2, 1, 'rect'],
            ['C2', 2, 'Tandoor counter', 16, 2, 2, 1, 'rect'],
            ['C3', 2, 'Tandoor counter', 16, 4, 2, 1, 'rect'],
            ['C4', 2, 'Tandoor counter', 16, 6, 2, 1, 'rect'],
            ['G1', 4, 'Garden', 17, 9, 2, 2, 'round'],
            ['G2', 4, 'Garden', 20, 9, 2, 2, 'round'],
            ['G3', 6, 'Garden', 19, 12, 3, 2, 'rect'],
        ];

        foreach ($plan as [$label, $seats, $area, $x, $y, $width, $height, $shape]) {
            DiningTable::create([
                'label' => $label,
                'seats' => $seats,
                'area' => $area,
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height,
                'shape' => $shape,
                'qr_token' => Str::random(32),
            ]);
        }
    }

    private function zones(): void
    {
        $zones = [
            ['Zone 1: Whitechapel and Bethnal Green', ['E1', 'E2'], 249, 1500, 25],
            ['Zone 2: Bow, Hackney and the Isle of Dogs', ['E3', 'E8', 'E14'], 349, 2000, 35],
            ['Zone 3: Clapton, Homerton and Islington', ['E5', 'E9', 'N1'], 449, 2500, 45],
        ];

        foreach ($zones as [$name, $prefixes, $fee, $minimum, $eta]) {
            DeliveryZone::create([
                'name' => $name,
                'postcode_prefixes' => $prefixes,
                'fee' => $fee,
                'minimum_order' => $minimum,
                'eta_minutes' => $eta,
            ]);
        }
    }

    private function settings(): void
    {
        $values = [
            ...config('restaurant.defaults'),
            'bank_holidays' => [
                '2026-01-01', '2026-04-03', '2026-04-06', '2026-05-04', '2026-05-25', '2026-08-31',
                '2026-12-25', '2026-12-28',
                '2027-01-01', '2027-03-26', '2027-03-29', '2027-05-03', '2027-05-31', '2027-08-30',
                '2027-12-27', '2027-12-28',
            ],
            'notification_channels' => ['mail' => true, 'sms' => true, 'whatsapp' => false],
            'receipt_footer' => 'Thank you for eating with us. Menu prices include VAT at 20%. A discretionary 12.5% service charge applies to dine-in bills and goes to the team. 14 Hanbury Street, London E1 6QR.',
        ];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        app(Settings::class)->flush();
    }
}

<?php

use App\Models\DeliveryZone;
use Tests\Feature\Policies\RoleMatrix;

/**
 * @return array<string, mixed>
 */
function policyZonePayload(DeliveryZone $zone, array $over = []): array
{
    return [
        'name' => $zone->name,
        'prefixes' => implode(', ', $zone->postcode_prefixes),
        'fee' => $zone->fee,
        'minimum_order' => $zone->minimum_order,
        'eta_minutes' => $zone->eta_minutes,
        'is_active' => $zone->is_active,
        ...$over,
    ];
}

dataset('policy settings writes', [
    'opening hours' => ['put', fn () => '/staff/settings/hours', RoleMatrix::MANAGEMENT],
    'bank holidays' => ['put', fn () => '/staff/settings/holidays', RoleMatrix::MANAGEMENT],
    'next year\'s bank holidays' => ['post', fn () => '/staff/settings/holidays/next-year', RoleMatrix::MANAGEMENT],
    'add a delivery zone' => ['post', fn () => '/staff/settings/zones', RoleMatrix::OWNER],
    'rename a delivery zone' => ['patch', function () {
        $zone = DeliveryZone::factory()->create();

        return ['/staff/settings/zones/'.$zone->id, policyZonePayload($zone, ['eta_minutes' => 40])];
    }, RoleMatrix::MANAGEMENT],
    'change a delivery fee' => ['patch', function () {
        $zone = DeliveryZone::factory()->create();

        return ['/staff/settings/zones/'.$zone->id, policyZonePayload($zone, ['fee' => $zone->fee + 100])];
    }, RoleMatrix::OWNER],
    'remove a delivery zone' => ['delete', fn () => '/staff/settings/zones/'.DeliveryZone::factory()->create()->id, RoleMatrix::OWNER],
    'VAT, service charge and deposits' => ['put', fn () => '/staff/settings/tax', RoleMatrix::OWNER],
    'receipts' => ['put', fn () => '/staff/settings/receipts', RoleMatrix::MANAGEMENT],
    'notifications' => ['put', fn () => '/staff/settings/notifications', RoleMatrix::MANAGEMENT],
    'hygiene rating' => ['put', fn () => '/staff/settings/hygiene', RoleMatrix::MANAGEMENT],
    'automations' => ['put', fn () => '/staff/settings/automations', RoleMatrix::MANAGEMENT],
]);

it('guards every settings write by role, keeping money settings for the owner', function (string $method, Closure $target, array $allowed, string $role) {
    RoleMatrix::check($this, $role, $method, $target, $allowed);
})->with('policy settings writes')->with(RoleMatrix::ROLES);

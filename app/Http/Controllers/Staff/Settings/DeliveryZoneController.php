<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Settings\DeliveryZoneRequest;
use App\Models\DeliveryZone;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryZoneController extends Controller
{
    public function store(DeliveryZoneRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->role === Role::Owner, 403);

        $zone = DeliveryZone::create($this->attributes($request));
        SettingsChange::log($request->user(), 'zones', "Added the delivery zone {$zone->name}", ['zone_id' => $zone->id, 'after' => $zone->only(array_keys($this->attributes($request)))]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_settings.toast.zone_added', ['name' => $zone->name])]);

        return back();
    }

    public function update(DeliveryZoneRequest $request, DeliveryZone $zone): RedirectResponse
    {
        $attributes = $this->attributes($request);

        abort_if(
            $request->user()?->role !== Role::Owner && ($attributes['fee'] !== $zone->fee || $attributes['minimum_order'] !== $zone->minimum_order),
            403,
        );

        $before = $zone->only(array_keys($attributes));
        $zone->update($attributes);

        if ($before !== $zone->only(array_keys($attributes))) {
            SettingsChange::log($request->user(), 'zones', "Changed the delivery zone {$zone->name}", ['zone_id' => $zone->id, 'before' => $before, 'after' => $zone->only(array_keys($attributes))]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_settings.toast.zone_saved', ['name' => $zone->name])]);

        return back();
    }

    public function destroy(Request $request, DeliveryZone $zone): RedirectResponse
    {
        if (Order::query()->where('delivery_zone_id', $zone->id)->exists()) {
            $zone->update(['is_active' => false]);
            $message = __('staff_settings.toast.zone_deactivated', ['name' => $zone->name]);
            $description = "Deactivated the delivery zone {$zone->name} (orders use it)";
        } else {
            $zone->delete();
            $message = __('staff_settings.toast.zone_removed', ['name' => $zone->name]);
            $description = "Removed the delivery zone {$zone->name}";
        }

        SettingsChange::log($request->user(), 'zones', $description, ['zone_id' => $zone->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * @return array{name: string, postcode_prefixes: list<string>, fee: int, minimum_order: int, eta_minutes: int, is_active: bool}
     */
    private function attributes(DeliveryZoneRequest $request): array
    {
        $data = $request->validated();

        return [
            'name' => $data['name'],
            'postcode_prefixes' => array_values($data['prefixes']),
            'fee' => (int) $data['fee'],
            'minimum_order' => (int) $data['minimum_order'],
            'eta_minutes' => (int) $data['eta_minutes'],
            'is_active' => (bool) $data['is_active'],
        ];
    }
}

<?php

namespace App\Http\Controllers\Staff\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Settings\DeliveryZoneRequest;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Setting;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DeliveryZoneController extends Controller
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function store(DeliveryZoneRequest $request): RedirectResponse
    {
        Gate::authorize('updateMoney', Setting::class);

        $attributes = $this->attributes($request);

        $zone = DB::transaction(function () use ($attributes, $request) {
            $zone = DeliveryZone::create($attributes);
            $this->activity->record('settings.zones', __('staff_settings.log.zone_added', ['name' => $zone->name]), null, ['zone_id' => $zone->id, 'after' => $zone->only(array_keys($attributes))], $request->user());

            return $zone;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_settings.toast.zone_added', ['name' => $zone->name])]);

        return back();
    }

    public function update(DeliveryZoneRequest $request, DeliveryZone $zone): RedirectResponse
    {
        $attributes = $this->attributes($request);

        // Fees and minimums set revenue, and pausing a zone stops delivery orders, so both are the owner's call.
        if ($attributes['fee'] !== $zone->fee || $attributes['minimum_order'] !== $zone->minimum_order || ($zone->is_active && ! $attributes['is_active'])) {
            Gate::authorize('updateMoney', Setting::class);
        }

        DB::transaction(function () use ($zone, $attributes, $request) {
            $before = $zone->only(array_keys($attributes));
            $zone->update($attributes);

            if ($before !== $zone->only(array_keys($attributes))) {
                $this->activity->record('settings.zones', __('staff_settings.log.zone_changed', ['name' => $zone->name]), null, ['zone_id' => $zone->id, 'before' => $before, 'after' => $zone->only(array_keys($attributes))], $request->user());
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_settings.toast.zone_saved', ['name' => $zone->name])]);

        return back();
    }

    public function destroy(Request $request, DeliveryZone $zone): RedirectResponse
    {
        Gate::authorize('updateMoney', Setting::class);

        $message = DB::transaction(function () use ($zone, $request) {
            if (Order::query()->where('delivery_zone_id', $zone->id)->exists()) {
                $zone->update(['is_active' => false]);
                $this->activity->record('settings.zones', __('staff_settings.log.zone_deactivated_used', ['name' => $zone->name]), null, ['zone_id' => $zone->id], $request->user());

                return __('staff_settings.toast.zone_deactivated', ['name' => $zone->name]);
            }

            $zone->delete();
            $this->activity->record('settings.zones', __('staff_settings.log.zone_removed', ['name' => $zone->name]), null, ['zone_id' => $zone->id], $request->user());

            return __('staff_settings.toast.zone_removed', ['name' => $zone->name]);
        });

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

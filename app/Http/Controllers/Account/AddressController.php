<?php

namespace App\Http\Controllers\Account;

use App\Actions\FindDeliveryZone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAddressRequest;
use App\Http\Requests\Account\UpdateAddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public const MAX = 10;

    public function index(Request $request, FindDeliveryZone $zones): Response
    {
        $user = $request->user();

        return Inertia::render('account/addresses', [
            'max' => self::MAX,
            'addresses' => Inertia::defer(fn () => $user->addresses()
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get()
                ->map(function (Address $address) use ($zones) {
                    $zone = $zones->handle($address->postcode);

                    return [
                        'id' => $address->id,
                        'label' => $address->label,
                        'line1' => $address->line1,
                        'line2' => $address->line2,
                        'city' => $address->city,
                        'postcode' => $address->postcode,
                        'instructions' => $address->instructions,
                        'is_default' => $address->is_default,
                        'zone' => $zone === null ? null : ['name' => $zone->name, 'fee' => $zone->fee],
                    ];
                })
                ->values()
                ->all()),
        ]);
    }

    public function store(StoreAddressRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $existing = $user->addresses()->lockForUpdate()->pluck('id');
            $default = $existing->isEmpty() || $request->boolean('is_default');

            if ($default) {
                $user->addresses()->update(['is_default' => false]);
            }

            $user->addresses()->create([...$this->fields($request), 'is_default' => $default]);
        });

        return $this->back('addresses.flash.added');
    }

    public function update(UpdateAddressRequest $request, Address $address): RedirectResponse
    {
        DB::transaction(function () use ($request, $address) {
            Address::query()->where('user_id', $address->user_id)->lockForUpdate()->get(['id']);
            $address->refresh();

            // The default can only move by choosing another one, so an account never has none.
            $default = $address->is_default || $request->boolean('is_default');

            if ($default && ! $address->is_default) {
                Address::query()->where('user_id', $address->user_id)->update(['is_default' => false]);
            }

            $address->update([...$this->fields($request), 'is_default' => $default]);
        });

        return $this->back('addresses.flash.updated');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('delete', $address);

        DB::transaction(function () use ($address) {
            $address->delete();

            if ($address->is_default) {
                Address::query()->where('user_id', $address->user_id)->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });

        return $this->back('addresses.flash.removed');
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(StoreAddressRequest|UpdateAddressRequest $request): array
    {
        $line2 = $request->input('line2');
        $instructions = $request->input('instructions');

        return [
            'label' => trim($request->string('label')->toString()),
            'line1' => trim($request->string('line1')->toString()),
            'line2' => is_string($line2) && trim($line2) !== '' ? trim($line2) : null,
            'city' => trim($request->string('city')->toString()),
            'postcode' => $request->string('postcode')->toString(),
            'instructions' => is_string($instructions) && trim($instructions) !== '' ? trim($instructions) : null,
        ];
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __($message)]);

        return redirect()->route('account.addresses.index');
    }
}

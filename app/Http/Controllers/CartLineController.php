<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddToCartRequest;
use App\Models\MenuItem;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartLineController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function store(AddToCartRequest $request): RedirectResponse
    {
        $item = MenuItem::findOrFail($request->integer('menu_item_id'));

        $this->cart->add(
            $item,
            $request->filled('item_variant_id') ? $request->integer('item_variant_id') : null,
            array_values(array_map('intval', (array) $request->input('modifier_ids', []))),
            $request->integer('quantity'),
            $request->string('notes')->toString() ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('cart.added', ['item' => $item->name])]);

        return back();
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_QUANTITY],
        ]);

        $this->cart->update($key, (int) $validated['quantity']);

        return back();
    }

    public function destroy(string $key): RedirectResponse
    {
        $this->cart->remove($key);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderHistoryController extends Controller
{
    private const PER_PAGE = 10;

    private const FILTERS = ['all', 'active', 'completed', 'cancelled'];

    public function __construct(private readonly CartService $cart) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = in_array($request->query('status'), self::FILTERS, true) ? (string) $request->query('status') : 'all';

        $query = $user->orders()->with('lines')->latest('placed_at')->latest('id');

        match ($filter) {
            'active' => $query->whereNotIn('status', AccountController::TERMINAL),
            'completed' => $query->where('status', OrderStatus::Completed),
            'cancelled' => $query->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Rejected]),
            default => null,
        };

        $page = $query->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('account/orders', [
            'filter' => $filter,
            'orders' => $page->getCollection()->map(fn (Order $order) => [
                'number' => $order->number,
                'status' => $order->status->value,
                'channel' => $order->channel->value,
                'placed_at' => $order->placed_at->toIso8601String(),
                'total' => $order->total,
                'lines' => $order->lines
                    ->map(fn (OrderLine $line) => ['name' => $line->name, 'quantity' => $line->quantity])
                    ->values()
                    ->all(),
            ])->values()->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'prev_url' => $page->previousPageUrl(),
                'next_url' => $page->nextPageUrl(),
            ],
        ]);
    }

    public function reorder(Order $order): RedirectResponse
    {
        Gate::authorize('reorder', $order);

        $order->load(['lines.menuItem.variants', 'lines.menuItem.modifierGroups.modifiers']);

        $added = 0;
        $soldOut = [];
        $skipped = [];
        $trimmed = [];

        foreach ($order->lines as $line) {
            $item = $line->menuItem;

            if ($item === null) {
                $skipped[] = $line->name;

                continue;
            }

            if (! $item->is_available) {
                $soldOut[] = $item->name;

                continue;
            }

            $choice = $this->resolve($line, $item, $trimmed);

            if ($choice === null) {
                $skipped[] = $item->name;

                continue;
            }

            try {
                $this->cart->add($item, $choice['variant'], $choice['modifiers'], $line->quantity, $line->notes);
                $added += $line->quantity;
            } catch (ValidationException) {
                $skipped[] = $item->name;
            }
        }

        $parts = [];

        if ($added > 0) {
            $parts[] = trans_choice('account.reorder.added', $added, ['count' => $added]);
        }

        if ($soldOut !== []) {
            $parts[] = trans_choice('account.reorder.sold_out', count(array_unique($soldOut)), ['items' => $this->list($soldOut)]);
        }

        if ($skipped !== []) {
            $parts[] = __('account.reorder.skipped', ['items' => $this->list($skipped)]);
        }

        if ($trimmed !== []) {
            $parts[] = __('account.reorder.trimmed', ['items' => $this->list($trimmed)]);
        }

        if ($parts === []) {
            $parts[] = __('account.reorder.nothing');
        }

        Inertia::flash('toast', [
            'type' => $added > 0 ? 'success' : 'info',
            'message' => implode(' ', $parts),
        ]);

        return $added > 0 ? redirect('/basket') : back();
    }

    /**
     * Matches an old line to today's menu. Orders keep names, not ids, for
     * modifiers, so those are matched by name inside the dish's groups.
     * Returns null when the dish needs a choice the old order cannot supply.
     *
     * @param  list<string>  $trimmed
     * @return array{variant: int|null, modifiers: list<int>}|null
     */
    private function resolve(OrderLine $line, MenuItem $item, array &$trimmed): ?array
    {
        $variantId = null;

        if ($item->variants->isNotEmpty()) {
            $variant = $item->variants->firstWhere('id', $line->item_variant_id)
                ?? $item->variants->firstWhere('name', $line->variant_name);

            if ($variant === null) {
                return null;
            }

            $variantId = $variant->id;
        }

        $wanted = collect($line->modifiers)->pluck('name');
        $ids = [];

        foreach ($item->modifierGroups as $group) {
            $matches = $group->modifiers
                ->filter(fn (Modifier $modifier) => $wanted->contains($modifier->name))
                ->values();

            if ($matches->count() < $group->min_select) {
                return null;
            }

            if ($matches->count() > $group->max_select) {
                $trimmed[] = $item->name;
                $matches = $matches->take($group->max_select);
            }

            array_push($ids, ...$matches->pluck('id')->all());
        }

        return ['variant' => $variantId, 'modifiers' => $ids];
    }

    /**
     * @param  list<string>  $names
     */
    private function list(array $names): string
    {
        return Arr::join(array_values(array_unique($names)), ', ', ' and ');
    }
}

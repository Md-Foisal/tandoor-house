<?php

namespace App\Http\Controllers;

use App\Actions\PlaceTableOrder;
use App\Actions\SplitBill;
use App\Actions\TableBill;
use App\Enums\OrderChannel;
use App\Enums\TableRequestType;
use App\Events\TableRequestRaised;
use App\Http\Requests\Table\BillRequest;
use App\Http\Requests\Table\SendOrderRequest;
use App\Http\Requests\Table\TableRequestRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\TableRequest;
use App\Services\CartService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ordering from a QR code on the table. Everything is scoped to one table;
 * a table merged into another always acts as its lead table.
 */
class TableOrderingController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly Settings $settings,
        private readonly TableBill $bill,
    ) {}

    public function show(DiningTable $table): Response|RedirectResponse
    {
        $lead = $this->lead($table);

        if ($lead->isNot($table)) {
            return redirect()->route('table.show', $lead->qr_token);
        }

        // Polled by the page, so only write when the basket is not already for this table.
        $cart = $this->cart->cart();

        if ($cart->channel !== OrderChannel::DineIn->value || $cart->dining_table_id !== $table->id) {
            $this->cart->useChannel(OrderChannel::DineIn, $table);
        }

        $summary = $this->cart->summary();

        return Inertia::render('table/show', [
            'table' => $this->tableData($table),
            'categories' => Inertia::defer(fn () => $this->categories()),
            'basket' => [
                'lines' => array_map(fn (array $line) => [
                    'key' => $line['key'],
                    'name' => $line['name'],
                    'variant' => $line['variant'],
                    'modifiers' => $line['modifiers'],
                    'notes' => $line['notes'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'total' => $line['total'],
                    'allergens' => $line['allergens'],
                    'is_available' => $line['is_available'],
                ], $summary['lines']),
                'count' => $summary['count'],
                'subtotal' => $summary['subtotal'],
                'service_charge' => $summary['service_charge'],
                'total' => $summary['total'],
                'has_unavailable' => $summary['has_unavailable'],
                'has_alcohol' => $summary['has_alcohol'],
            ],
            'service_rate' => (int) $this->settings->get('service_charge_rate'),
            'orders' => fn () => $this->bill->visit($table)->map(fn (Order $order) => $this->orderData($order))->values()->all(),
            'requests' => fn () => $this->openRequests($table),
        ]);
    }

    public function request(TableRequestRequest $request, DiningTable $table): RedirectResponse
    {
        $table = $this->lead($table);
        $type = TableRequestType::from($request->string('type')->toString());

        $open = $table->requests()->where('type', $type->value)->whereNull('handled_at')->exists();

        if ($open) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('table.toast.duplicate')]);

            return back()->withErrors(['type' => __('table.errors.duplicate')]);
        }

        TableRequestRaised::dispatch($table->requests()->create(['type' => $type]));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $type === TableRequestType::Bill ? __('table.toast.bill') : __('table.toast.waiter'),
        ]);

        return back();
    }

    public function bill(BillRequest $request, DiningTable $table, SplitBill $splitter): Response|RedirectResponse
    {
        $lead = $this->lead($table);

        if ($lead->isNot($table)) {
            return redirect()->route('table.bill', $lead->qr_token);
        }

        $orders = $this->bill->visit($table);
        $units = $this->units($orders);
        $food = array_sum(array_column($units, 'price'));
        $position = $this->bill->position($table, $orders);
        $serviceCharge = (int) $orders->sum('service_charge');
        $includeService = $request->boolean('service', true);
        $total = $position['gross'] - ($includeService ? 0 : $serviceCharge);
        $left = $this->bill->payable($position, $includeService);

        $mode = $request->string('mode')->toString() ?: 'none';
        $people = $request->integer('people', 2);
        $guests = $request->integer('guests', 2);
        /** @var array<string, int> $claims */
        $claims = collect((array) $request->input('claims', []))
            ->filter(fn ($guest, $key) => $guest !== null && $guest !== '' && (int) $guest < $guests && isset($units[$key]))
            ->map(fn ($guest) => (int) $guest)
            ->all();

        $split = match ($mode) {
            'even' => ['shares' => $splitter->evenly($left, $people), 'unclaimed' => 0],
            'items' => $splitter->byItems($left, $food, $this->guestFood($units, $claims, $guests)),
            default => null,
        };

        return Inertia::render('table/bill', [
            'table' => $this->tableData($table),
            'orders' => $orders->map(fn (Order $order) => $this->orderData($order, true))->values()->all(),
            'units' => array_values($units),
            'totals' => [
                'subtotal' => (int) $orders->sum('subtotal'),
                'service_charge' => $serviceCharge,
                'service_included' => $includeService,
                'vat_total' => (int) $orders->sum('vat_total'),
                'total' => $total,
                'paid' => max(0, $total - $left),
                'left' => $left,
            ],
            'tip_presets' => array_values((array) $this->settings->get('tip_presets')),
            'service_rate' => (int) $this->settings->get('service_charge_rate'),
            'options' => [
                'service' => $includeService,
                'mode' => $mode,
                'people' => $people,
                'guests' => $guests,
                'claims' => (object) $claims,
            ],
            'split' => $split,
            'requests' => $this->openRequests($table),
        ]);
    }

    public function order(SendOrderRequest $request, DiningTable $table, PlaceTableOrder $place): RedirectResponse
    {
        $table = $this->lead($table);
        $this->cart->useChannel(OrderChannel::DineIn, $table);
        $summary = $this->cart->summary();

        if ($summary['count'] === 0) {
            return back()->withErrors(['basket' => __('table.errors.empty')]);
        }

        if ($summary['has_unavailable']) {
            return back()->withErrors(['basket' => __('table.errors.sold_out')]);
        }

        $place->handle($table, $request->user(), $request->boolean('age_confirmed'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('table.toast.sent')]);

        return redirect()->route('table.show', $table->qr_token);
    }

    private function lead(DiningTable $table): DiningTable
    {
        for ($hops = 0; $table->merged_into_id !== null && $hops < 5; $hops++) {
            $table = DiningTable::query()->findOrFail($table->merged_into_id);
        }

        return $table;
    }

    /**
     * Each unit of each line is its own item, so two guests can share a
     * portion of three naans.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<string, array{key: string, name: string, detail: string|null, price: int, number: int, count: int}>
     */
    private function units(Collection $orders): array
    {
        $units = [];

        foreach ($orders as $order) {
            foreach ($order->lines as $line) {
                for ($n = 1; $n <= $line->quantity; $n++) {
                    $key = $line->id.'-'.$n;
                    $units[$key] = [
                        'key' => $key,
                        'name' => $line->name,
                        'detail' => $this->detail($line),
                        'price' => $line->unit_price,
                        'number' => $n,
                        'count' => $line->quantity,
                    ];
                }
            }
        }

        return $units;
    }

    /**
     * @param  array<string, array{price: int}>  $units
     * @param  array<string, int>  $claims
     * @return list<int>
     */
    private function guestFood(array $units, array $claims, int $guests): array
    {
        $food = array_fill(0, $guests, 0);

        foreach ($claims as $key => $guest) {
            $food[$guest] += $units[$key]['price'];
        }

        return array_values($food);
    }

    private function detail(OrderLine $line): ?string
    {
        $parts = array_filter([
            $line->variant_name,
            ...array_column($line->modifiers, 'name'),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderData(Order $order, bool $withLines = false): array
    {
        $data = [
            'number' => $order->number,
            'status' => $order->status->value,
            'placed_label' => $order->placed_at->timezone('Europe/London')->format('H:i'),
            'item_count' => (int) $order->lines->sum('quantity'),
            'total' => $order->total,
        ];

        if ($withLines) {
            $data['lines'] = $order->lines->map(fn (OrderLine $line) => [
                'id' => $line->id,
                'name' => $line->name,
                'detail' => $this->detail($line),
                'quantity' => $line->quantity,
                'line_total' => $line->unit_price * $line->quantity,
            ])->values()->all();
        } else {
            $data['summary'] = $order->lines->map(fn (OrderLine $line) => $line->quantity.' × '.$line->name)->implode(', ');
        }

        return $data;
    }

    /**
     * @return array{call_waiter: string|null, bill: string|null}
     */
    private function openRequests(DiningTable $table): array
    {
        $open = $table->requests()->whereNull('handled_at')->get()->keyBy(fn (TableRequest $request) => $request->type->value);

        return [
            'call_waiter' => $open->get('call_waiter')?->created_at?->timezone('Europe/London')->format('H:i'),
            'bill' => $open->get('bill')?->created_at?->timezone('Europe/London')->format('H:i'),
        ];
    }

    /**
     * @return array{token: string, label: string, area: string}
     */
    private function tableData(DiningTable $table): array
    {
        return ['token' => $table->qr_token, 'label' => $table->label, 'area' => $table->area];
    }

    /**
     * Same shape and cache key as the public menu page.
     *
     * @return array<int, mixed>
     */
    private function categories(): array
    {
        return Cache::remember(MenuController::CACHE_KEY, 600, function (): array {
            return Category::query()
                ->orderBy('position')
                ->with(['items' => fn ($query) => $query->with(['variants', 'modifierGroups.modifiers'])])
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'description' => $category->description,
                    'items' => json_decode(json_encode(MenuItemResource::collection($category->items)->resolve(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR),
                ])
                ->all();
        });
    }
}

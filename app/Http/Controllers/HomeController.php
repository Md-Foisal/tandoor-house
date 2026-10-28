<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Review;
use App\Services\OpeningHours;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const SPECIALS = 3;

    private const REVIEWS = 6;

    private const RECENT_ORDERS = 3;

    public function __invoke(Request $request, OpeningHours $hours, Settings $settings): Response
    {
        $user = $request->user();

        return Inertia::render('home', [
            'specials' => fn () => MenuItemResource::collection(
                MenuItem::query()
                    ->where('is_special', true)
                    ->with('variants', 'modifierGroups.modifiers')
                    ->orderByDesc('is_available')
                    ->orderBy('position')
                    ->limit(self::SPECIALS)
                    ->get(),
            )->resolve($request),
            'recentOrders' => $user?->role === Role::Customer
                ? $this->recentOrders($user->id)
                : null,
            'hours' => $hours->week(),
            'directionsUrl' => $this->directionsUrl(),
            'reviews' => Inertia::defer(fn () => $this->reviews(), 'reviews'),
            'numbers' => Inertia::defer(fn () => $this->numbers($settings), 'numbers'),
        ]);
    }

    /**
     * @return array<int, array{number: string, placed_at: string, total: int, lines: array<int, array{name: string, quantity: int}>}>
     */
    private function recentOrders(int $userId): array
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('status', OrderStatus::Completed)
            ->with('lines')
            ->latest('placed_at')
            ->limit(self::RECENT_ORDERS)
            ->get()
            ->map(fn (Order $order) => [
                'number' => $order->number,
                'placed_at' => $order->placed_at->toIso8601String(),
                'total' => $order->total,
                'lines' => $order->lines
                    ->map(fn (OrderLine $line) => ['name' => $line->name, 'quantity' => $line->quantity])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, author_name: string, author_area: string|null, rating: int, body: string}>
     */
    private function reviews(): array
    {
        return Review::query()
            ->where('is_published', true)
            ->latest()
            ->limit(self::REVIEWS)
            ->get(['id', 'author_name', 'author_area', 'rating', 'body'])
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'author_name' => $review->author_name,
                'author_area' => $review->author_area,
                'rating' => $review->rating,
                'body' => $review->body,
            ])
            ->all();
    }

    /**
     * @return array{years: int, rating: float|null, reviews: int, dishes: int}
     */
    private function numbers(Settings $settings): array
    {
        $reviews = Review::query()->where('is_published', true);
        $average = (clone $reviews)->avg('rating');

        $dishes = OrderLine::query()
            ->whereHas('order', fn ($query) => $query
                ->where('status', OrderStatus::Completed)
                ->where('placed_at', '>=', now()->startOfYear()))
            ->sum('quantity');

        return [
            'years' => max(1, now()->year - (int) $settings->get('opened_year', 2016)),
            'rating' => $average === null ? null : round((float) $average, 1),
            'reviews' => $reviews->count(),
            'dishes' => (int) $dishes,
        ];
    }

    private function directionsUrl(): string
    {
        $address = (array) config('restaurant.contact.address');

        return 'https://www.google.com/maps/dir/?'.http_build_query([
            'api' => 1,
            'destination' => implode(', ', array_filter([
                config('restaurant.name'),
                $address['line1'] ?? null,
                $address['city'] ?? null,
                $address['postcode'] ?? null,
            ])),
        ]);
    }
}

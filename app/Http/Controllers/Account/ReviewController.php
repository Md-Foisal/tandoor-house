<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreReviewRequest;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    private const WINDOW_DAYS = 60;

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('account/reviews', [
            'waiting' => Inertia::defer(fn () => $user->orders()
                ->with('lines')
                ->where('status', OrderStatus::Completed)
                ->where('placed_at', '>=', now()->subDays(self::WINDOW_DAYS))
                ->whereDoesntHave('review')
                ->latest('placed_at')
                ->get()
                ->map(fn (Order $order) => [
                    'number' => $order->number,
                    'placed_at' => $order->placed_at->toIso8601String(),
                    'date_label' => $order->placed_at->timezone(config('app.timezone'))->translatedFormat('l j F'),
                    'items' => $order->lines->map(fn (OrderLine $line) => $line->quantity.' × '.$line->name)->values()->all(),
                ])
                ->values()
                ->all(), 'reviews'),
            'reviews' => Inertia::defer(fn () => $user->reviews()
                ->with('order')
                ->latest()
                ->get()
                ->map(fn (Review $review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'body' => $review->body,
                    'author_name' => $review->author_name,
                    'author_area' => $review->author_area,
                    'reply' => $review->reply,
                    'is_published' => $review->is_published,
                    'order_number' => $review->order?->number,
                    'date_label' => $review->created_at?->timezone(config('app.timezone'))->translatedFormat('j F Y'),
                ])
                ->values()
                ->all(), 'reviews'),
        ]);
    }

    public function store(StoreReviewRequest $request, Order $order): RedirectResponse
    {
        $user = $request->user();

        $order->review()->create([
            'user_id' => $user->id,
            'author_name' => $this->authorName($user),
            'author_area' => $request->input('author_area'),
            'rating' => $request->integer('rating'),
            'body' => trim($request->string('body')->toString()),
            'is_published' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('reviews.flash.sent')]);

        return redirect()->route('account.reviews.index');
    }

    /**
     * "Priya Sharma" becomes "Priya S."; a single name stays as it is.
     */
    private function authorName(User $user): string
    {
        $parts = explode(' ', (string) Str::of($user->name)->squish());
        $first = $parts[0];

        if (count($parts) < 2) {
            return $first;
        }

        return $first.' '.mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)).'.';
    }
}

<?php

namespace App\Http\Controllers\Staff\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reviews\ReplyRequest;
use App\Models\ActivityLog;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ReviewBoardController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), ReviewQueue::TABS, true) ? (string) $request->query('tab') : 'waiting';
        $counts = ReviewQueue::counts();

        return Inertia::render('staff/reviews/index', [
            'tab' => $tab,
            'counts' => $counts,
            'reply_max' => ReplyRequest::MAX,
            'hide_reasons' => ReviewQueue::HIDE_REASONS,
            'reviews' => Inertia::defer(fn () => $this->page($tab, max(1, $request->integer('page', 1)), $counts[$tab])),
            'summary' => Inertia::defer(fn () => ReviewSummary::build(), 'summary'),
        ]);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, page: int, last_page: int, total: int}
     */
    private function page(string $tab, int $page, int $total): array
    {
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);

        $reviews = ReviewQueue::tab(Review::query(), $tab)
            ->with('order:id,number,channel,placed_at', 'order.lines:id,order_id,name,quantity')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        $hidden = $tab === 'hidden' ? $this->hiddenDetails($reviews->getCollection()) : collect();

        return [
            'data' => $reviews->getCollection()->map(fn (Review $review) => $this->row($review, $hidden->get($review->id)))->values()->all(),
            'page' => $page,
            'last_page' => $lastPage,
            'total' => $reviews->total(),
        ];
    }

    /**
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int|string, array{reason: mixed, note: mixed, by: string|null, at: string}>
     */
    private function hiddenDetails(Collection $reviews): Collection
    {
        return ActivityLog::query()
            ->with('user:id,name')
            ->where('subject_type', (new Review)->getMorphClass())
            ->where('event', ReviewQueue::HIDDEN)
            ->whereIn('subject_id', $reviews->pluck('id'))
            ->orderBy('id')
            ->get()
            ->keyBy('subject_id')
            ->map(fn (ActivityLog $log) => [
                'reason' => $log->properties['reason'] ?? null,
                'note' => $log->properties['note'] ?? null,
                'by' => $log->user?->name,
                'at' => $log->created_at->toIso8601String(),
            ]);
    }

    /**
     * @param  array<string, mixed>|null  $hidden
     * @return array<string, mixed>
     */
    private function row(Review $review, ?array $hidden): array
    {
        $order = $review->order;

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'body' => $review->body,
            'author_name' => $review->author_name,
            'author_area' => $review->author_area,
            'created_at' => $review->created_at?->toIso8601String(),
            'reply' => $review->reply,
            'is_published' => $review->is_published,
            'hidden' => $hidden,
            'order' => $order ? [
                'number' => $order->number,
                'channel' => $order->channel->value,
                'placed_at' => $order->placed_at->toIso8601String(),
                'items' => $order->lines->map(fn ($line) => "{$line->quantity} × {$line->name}")->values()->all(),
            ] : null,
        ];
    }
}

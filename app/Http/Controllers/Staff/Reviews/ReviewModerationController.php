<?php

namespace App\Http\Controllers\Staff\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reviews\BulkPublishRequest;
use App\Http\Requests\Staff\Reviews\HideReviewRequest;
use App\Models\Review;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReviewModerationController extends Controller
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function publish(Request $request, Review $review): RedirectResponse
    {
        DB::transaction(function () use ($review, $request) {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_published) {
                $this->publishOne($locked, $request);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_reviews.flash.published', ['name' => $review->author_name])]);

        return back();
    }

    public function hide(HideReviewRequest $request, Review $review): RedirectResponse
    {
        $reason = $request->string('reason')->toString();
        $note = trim($request->string('note')->toString());

        DB::transaction(function () use ($review, $reason, $note, $request) {
            $review->update(['is_published' => false]);

            $this->activity->record(
                ReviewQueue::HIDDEN,
                __('staff_reviews.log.hidden', ['label' => ReviewQueue::label($review), 'reason' => __("staff_reviews.reasons.{$reason}")]),
                $review,
                ['reason' => $reason, 'note' => $note !== '' ? $note : null],
                $request->user(),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_reviews.flash.hidden', ['name' => $review->author_name])]);

        return back();
    }

    public function bulkPublish(BulkPublishRequest $request): RedirectResponse
    {
        $reviews = DB::transaction(function () use ($request) {
            $reviews = ReviewQueue::tab(Review::query(), 'waiting')->whereIn('id', $request->array('ids'))->lockForUpdate()->get();

            foreach ($reviews as $review) {
                $this->publishOne($review, $request, bulk: true);
            }

            return $reviews;
        });

        if ($reviews->isEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('staff_reviews.flash.bulk_none')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('staff_reviews.flash.bulk_published', $reviews->count(), ['count' => $reviews->count()])]);

        return back();
    }

    private function publishOne(Review $review, Request $request, bool $bulk = false): void
    {
        $review->update(['is_published' => true]);

        $this->activity->record(
            ReviewQueue::PUBLISHED,
            __('staff_reviews.log.published', ['label' => ReviewQueue::label($review)]),
            $review,
            ['bulk' => $bulk],
            $request->user(),
        );
    }
}

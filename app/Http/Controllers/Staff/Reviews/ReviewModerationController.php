<?php

namespace App\Http\Controllers\Staff\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reviews\BulkPublishRequest;
use App\Http\Requests\Staff\Reviews\HideReviewRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewModerationController extends Controller
{
    public function publish(Request $request, Review $review): RedirectResponse
    {
        if (! $review->is_published) {
            $this->publishOne($review, $request);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_reviews.flash.published', ['name' => $review->author_name])]);

        return back();
    }

    public function hide(HideReviewRequest $request, Review $review): RedirectResponse
    {
        $reason = $request->string('reason')->toString();
        $note = trim($request->string('note')->toString());

        $review->update(['is_published' => false]);

        ReviewQueue::log(
            ReviewQueue::HIDDEN,
            $review,
            $request->user(),
            'Hid a '.ReviewQueue::label($review).' ('.__("staff_reviews.reasons.{$reason}").')',
            ['reason' => $reason, 'note' => $note !== '' ? $note : null],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_reviews.flash.hidden', ['name' => $review->author_name])]);

        return back();
    }

    public function bulkPublish(BulkPublishRequest $request): RedirectResponse
    {
        $reviews = ReviewQueue::tab(Review::query(), 'waiting')->whereIn('id', $request->array('ids'))->get();

        if ($reviews->isEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('staff_reviews.flash.bulk_none')]);

            return back();
        }

        foreach ($reviews as $review) {
            $this->publishOne($review, $request, bulk: true);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('staff_reviews.flash.bulk_published', $reviews->count(), ['count' => $reviews->count()])]);

        return back();
    }

    private function publishOne(Review $review, Request $request, bool $bulk = false): void
    {
        $review->update(['is_published' => true]);

        ReviewQueue::log(
            ReviewQueue::PUBLISHED,
            $review,
            $request->user(),
            'Published a '.ReviewQueue::label($review),
            ['bulk' => $bulk],
        );
    }
}

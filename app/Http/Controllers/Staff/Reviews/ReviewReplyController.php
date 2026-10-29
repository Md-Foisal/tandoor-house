<?php

namespace App\Http\Controllers\Staff\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reviews\ReplyRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewReplyController extends Controller
{
    public function update(ReplyRequest $request, Review $review): RedirectResponse
    {
        $text = $request->string('reply')->toString();
        $edited = $review->reply !== null;
        $previous = $review->reply;

        $review->update(['reply' => $text]);

        ReviewQueue::log(
            $edited ? 'review.reply_edited' : 'review.replied',
            $review,
            $request->user(),
            ($edited ? 'Edited the reply to a ' : 'Replied to a ').ReviewQueue::label($review),
            ['reply' => $text, 'previous' => $previous],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __($edited ? 'staff_reviews.flash.reply_edited' : 'staff_reviews.flash.replied')]);

        return back();
    }

    public function destroy(Request $request, Review $review): RedirectResponse
    {
        if ($review->reply !== null) {
            $previous = $review->reply;
            $review->update(['reply' => null]);

            ReviewQueue::log(
                'review.reply_removed',
                $review,
                $request->user(),
                'Removed the reply to a '.ReviewQueue::label($review),
                ['previous' => $previous],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_reviews.flash.reply_removed')]);

        return back();
    }
}

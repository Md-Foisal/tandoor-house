<?php

namespace App\Http\Controllers\Staff\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reviews\ReplyRequest;
use App\Models\Review;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ReviewReplyController extends Controller
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function update(ReplyRequest $request, Review $review): RedirectResponse
    {
        $text = $request->string('reply')->toString();
        $edited = false;

        DB::transaction(function () use ($review, $text, $request, &$edited) {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $edited = $locked->reply !== null;
            $previous = $locked->reply;

            $locked->update(['reply' => $text]);

            $this->activity->record(
                $edited ? 'review.reply_edited' : 'review.replied',
                __($edited ? 'staff_reviews.log.reply_edited' : 'staff_reviews.log.replied', ['label' => ReviewQueue::label($locked)]),
                $locked,
                ['reply' => $text, 'previous' => $previous],
                $request->user(),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __($edited ? 'staff_reviews.flash.reply_edited' : 'staff_reviews.flash.replied')]);

        return back();
    }

    public function destroy(Request $request, Review $review): RedirectResponse
    {
        Gate::authorize('removeReply', $review);

        DB::transaction(function () use ($review, $request) {
            $locked = Review::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($locked->reply === null) {
                return;
            }

            $previous = $locked->reply;
            $locked->update(['reply' => null]);

            $this->activity->record(
                'review.reply_removed',
                __('staff_reviews.log.reply_removed', ['label' => ReviewQueue::label($locked)]),
                $locked,
                ['previous' => $previous],
                $request->user(),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_reviews.flash.reply_removed')]);

        return back();
    }
}

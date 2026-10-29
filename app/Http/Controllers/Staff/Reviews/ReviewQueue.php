<?php

namespace App\Http\Controllers\Staff\Reviews;

use App\Models\ActivityLog;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where a review sits in moderation. There is no "hidden" column: a hidden
 * review is unpublished and its latest publish/hide log entry says "hidden".
 */
class ReviewQueue
{
    public const HIDDEN = 'review.hidden';

    public const PUBLISHED = 'review.published';

    public const HIDE_REASONS = ['abusive', 'personal_data', 'not_about_us', 'other'];

    public const TABS = ['waiting', 'published', 'hidden'];

    /**
     * Ids of reviews whose latest publish/hide decision was "hide".
     *
     * @return Builder<ActivityLog>
     */
    public static function hiddenIds(): Builder
    {
        $type = (new Review)->getMorphClass();

        return ActivityLog::query()
            ->select('subject_id')
            ->where('subject_type', $type)
            ->where('event', self::HIDDEN)
            ->whereIn('id', ActivityLog::query()
                ->selectRaw('max(id)')
                ->where('subject_type', $type)
                ->whereIn('event', [self::HIDDEN, self::PUBLISHED])
                ->groupBy('subject_id'));
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public static function tab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'published' => $query->where('is_published', true),
            'hidden' => $query->where('is_published', false)->whereIn('id', self::hiddenIds()),
            default => $query->where('is_published', false)->whereNotIn('id', self::hiddenIds()),
        };
    }

    /**
     * @return array{waiting: int, published: int, hidden: int}
     */
    public static function counts(): array
    {
        return [
            'waiting' => self::tab(Review::query(), 'waiting')->count(),
            'published' => self::tab(Review::query(), 'published')->count(),
            'hidden' => self::tab(Review::query(), 'hidden')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $event, Review $review, User $by, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'event' => $event,
            'subject_type' => $review->getMorphClass(),
            'subject_id' => $review->id,
            'user_id' => $by->id,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    public static function label(Review $review): string
    {
        return "{$review->rating}-star review from {$review->author_name}";
    }
}

<?php

namespace App\Http\Controllers\Staff\Reviews;

use App\Models\Review;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Numbers that help the manager see how the last month went. Hidden reviews
 * are left out so one abusive post cannot drag the average.
 */
class ReviewSummary
{
    private const STOP_WORDS = [
        'the', 'and', 'for', 'was', 'were', 'are', 'but', 'not', 'had', 'has', 'have', 'with', 'this', 'that',
        'they', 'them', 'there', 'their', 'our', 'out', 'you', 'your', 'its', 'it\'s', 'from', 'about', 'when',
        'what', 'which', 'would', 'could', 'should', 'been', 'being', 'than', 'then', 'too', 'very', 'just',
        'also', 'all', 'any', 'one', 'got', 'get', 'came', 'come', 'went', 'will', 'did', 'does', 'didn\'t',
        'wasn\'t', 'was', 'only', 'some', 'more', 'much', 'into', 'over', 'after', 'before', 'again', 'because',
        'while', 'who', 'how', 'why', 'here', 'she', 'him', 'her', 'his', 'can', 'can\'t', 'don\'t', 'like',
        'really', 'quite', 'bit', 'few', 'order', 'ordered', 'food', 'restaurant',
    ];

    /**
     * @return array{
     *     average: float|null, previous_average: float|null, change: float|null,
     *     count: int, previous_count: int, stars: array<int, int>,
     *     themes: list<array{word: string, count: int}>
     * }
     */
    public static function build(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $thirty = $now->subDays(30);
        $sixty = $now->subDays(60);

        $visible = fn (): Builder => Review::query()->whereNotIn('id', ReviewQueue::hiddenIds());

        $current = $visible()->where('created_at', '>', $thirty)->where('created_at', '<=', $now)->pluck('rating');
        $previous = $visible()->where('created_at', '>', $sixty)->where('created_at', '<=', $thirty)->pluck('rating');

        $average = $current->isEmpty() ? null : round($current->avg(), 1);
        $previousAverage = $previous->isEmpty() ? null : round($previous->avg(), 1);

        $stars = [];

        for ($star = 5; $star >= 1; $star--) {
            $stars[$star] = $current->filter(fn ($rating) => $rating === $star)->count();
        }

        $low = $visible()->where('rating', '<=', 3)->where('created_at', '>', $now->subDays(90))->pluck('body');

        return [
            'average' => $average,
            'previous_average' => $previousAverage,
            'change' => $average !== null && $previousAverage !== null ? round($average - $previousAverage, 1) : null,
            'count' => $current->count(),
            'previous_count' => $previous->count(),
            'stars' => $stars,
            'themes' => self::themes($low->all()),
        ];
    }

    /**
     * The words that turn up in the most reviews, once per review, skipping
     * filler. A word needs at least two reviews to count as a theme.
     *
     * @param  array<int, string>  $bodies
     * @return list<array{word: string, count: int}>
     */
    public static function themes(array $bodies, int $limit = 8): array
    {
        $counts = [];

        foreach ($bodies as $body) {
            $words = preg_split("/[^\p{L}']+/u", mb_strtolower($body), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $seen = [];

            foreach ($words as $word) {
                $word = trim($word, "'");

                if (mb_strlen($word) < 3 || in_array($word, self::STOP_WORDS, true) || isset($seen[$word])) {
                    continue;
                }

                $seen[$word] = true;
                $counts[$word] = ($counts[$word] ?? 0) + 1;
            }
        }

        $counts = array_filter($counts, fn (int $count) => $count >= 2);
        uksort($counts, fn (string $a, string $b) => [$counts[$b], $a] <=> [$counts[$a], $b]);

        $themes = [];

        foreach (array_slice($counts, 0, $limit, true) as $word => $count) {
            $themes[] = ['word' => (string) $word, 'count' => $count];
        }

        return $themes;
    }
}

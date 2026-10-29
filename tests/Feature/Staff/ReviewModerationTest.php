<?php

use App\Enums\Role;
use App\Http\Controllers\Staff\Reviews\ReviewSummary;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Review;
use App\Models\User;

beforeEach(fn () => $this->travelTo('2026-10-28 18:00:00'));

function reviewer(Role $role = Role::Manager): User
{
    return User::factory()->role($role)->create();
}

function logsFor(Review $review, string $event): int
{
    return ActivityLog::query()->where('event', $event)->where('subject_id', $review->id)->count();
}

function hidden(Review $review, User $by): void
{
    test()->actingAs($by)->post("/staff/reviews/{$review->id}/hide", ['reason' => 'abusive'])->assertRedirect();
}

it('lets managers and owners in and nobody else', function (Role $role, int $status) {
    $this->actingAs(reviewer($role))->get('/staff/reviews')->assertStatus($status);
})->with([
    [Role::Manager, 200], [Role::Owner, 200],
    [Role::Waiter, 403], [Role::Cashier, 403], [Role::Kitchen, 403], [Role::Rider, 403], [Role::Customer, 403],
]);

it('keeps every write behind the reviews area', function () {
    $review = Review::factory()->unpublished()->create();

    $this->actingAs(reviewer(Role::Waiter));
    $this->post("/staff/reviews/{$review->id}/publish")->assertForbidden();
    $this->post("/staff/reviews/{$review->id}/hide", ['reason' => 'abusive'])->assertForbidden();
    $this->post('/staff/reviews/publish', ['ids' => [$review->id]])->assertForbidden();
    $this->put("/staff/reviews/{$review->id}/reply", ['reply' => 'Hi'])->assertForbidden();
    $this->delete("/staff/reviews/{$review->id}/reply")->assertForbidden();

    expect($review->fresh()->is_published)->toBeFalse()->and($review->fresh()->reply)->toBeNull();
});

it('puts each review on the right tab and counts them', function () {
    $manager = reviewer();
    $waiting = Review::factory()->unpublished()->create();
    $published = Review::factory()->create();
    $hiddenOne = Review::factory()->create();
    hidden($hiddenOne, $manager);

    $get = fn (string $query = '') => $this->actingAs($manager)->get('/staff/reviews'.$query);

    $get()->assertInertia(fn ($page) => $page
        ->where('tab', 'waiting')
        ->where('counts', ['waiting' => 1, 'published' => 1, 'hidden' => 1])
        ->loadDeferredProps(fn ($page) => $page->has('reviews.data', 1)->where('reviews.data.0.id', $waiting->id)));

    $get('?tab=published')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page->has('reviews.data', 1)->where('reviews.data.0.id', $published->id)));

    $get('?tab=hidden')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page->has('reviews.data', 1)
            ->where('reviews.data.0.id', $hiddenOne->id)
            ->where('reviews.data.0.hidden.reason', 'abusive')
            ->where('reviews.data.0.hidden.by', $manager->name)));
});

it('shows the order a review came from', function () {
    $order = Order::factory()->create();
    OrderLine::factory()->create(['order_id' => $order->id, 'name' => 'Butter chicken', 'quantity' => 2]);
    Review::factory()->unpublished()->create(['order_id' => $order->id]);

    $this->actingAs(reviewer())->get('/staff/reviews')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page
            ->where('reviews.data.0.order.number', $order->number)
            ->where('reviews.data.0.order.items', ['2 × Butter chicken'])));
});

it('publishes a review and logs it', function () {
    $manager = reviewer();
    $review = Review::factory()->unpublished()->create(['rating' => 4]);

    $this->actingAs($manager)->post("/staff/reviews/{$review->id}/publish")->assertRedirect();

    expect($review->fresh()->is_published)->toBeTrue();
    $log = ActivityLog::query()->where('event', 'review.published')->firstOrFail();
    expect($log->user_id)->toBe($manager->id)->and($log->subject_id)->toBe($review->id);
});

it('hides with a reason, logs it, and needs a valid reason', function () {
    $manager = reviewer();
    $review = Review::factory()->create();

    $this->actingAs($manager)->post("/staff/reviews/{$review->id}/hide", [])->assertSessionHasErrors('reason');
    $this->post("/staff/reviews/{$review->id}/hide", ['reason' => 'rude'])->assertSessionHasErrors('reason');
    expect($review->fresh()->is_published)->toBeTrue();

    $this->post("/staff/reviews/{$review->id}/hide", ['reason' => 'personal_data', 'note' => 'Has a phone number'])->assertRedirect();

    expect($review->fresh()->is_published)->toBeFalse();
    $log = ActivityLog::query()->where('event', 'review.hidden')->firstOrFail();
    expect($log->properties)->toMatchArray(['reason' => 'personal_data', 'note' => 'Has a phone number']);
});

it('moves a review back out of hidden when it is published again', function () {
    $manager = reviewer();
    $review = Review::factory()->create();
    hidden($review, $manager);

    expect(Review::query()->where('is_published', false)->count())->toBe(1);

    $this->actingAs($manager)->get('/staff/reviews')->assertInertia(fn ($page) => $page->where('counts.hidden', 1)->where('counts.waiting', 0));

    $this->post("/staff/reviews/{$review->id}/publish")->assertRedirect();

    $this->get('/staff/reviews')->assertInertia(fn ($page) => $page
        ->where('counts', ['waiting' => 0, 'published' => 1, 'hidden' => 0]));
});

it('replies, edits and removes a reply, logging each step', function () {
    $manager = reviewer();
    $review = Review::factory()->create();

    $this->actingAs($manager)->put("/staff/reviews/{$review->id}/reply", ['reply' => '  Thank you, see you soon.  '])->assertRedirect();
    expect($review->fresh()->reply)->toBe('Thank you, see you soon.')->and(logsFor($review, 'review.replied'))->toBe(1);

    $this->put("/staff/reviews/{$review->id}/reply", ['reply' => 'Thank you.'])->assertRedirect();
    expect($review->fresh()->reply)->toBe('Thank you.')->and(logsFor($review, 'review.reply_edited'))->toBe(1);

    $this->delete("/staff/reviews/{$review->id}/reply")->assertRedirect();
    expect($review->fresh()->reply)->toBeNull()->and(logsFor($review, 'review.reply_removed'))->toBe(1);
});

it('limits a reply to 600 characters and rejects an empty one', function () {
    $review = Review::factory()->create();
    $this->actingAs(reviewer());

    $this->put("/staff/reviews/{$review->id}/reply", ['reply' => str_repeat('a', 601)])->assertSessionHasErrors('reply');
    $this->put("/staff/reviews/{$review->id}/reply", ['reply' => '   '])->assertSessionHasErrors('reply');
    expect($review->fresh()->reply)->toBeNull();

    $this->put("/staff/reviews/{$review->id}/reply", ['reply' => str_repeat('a', 600)])->assertSessionDoesntHaveErrors();
    expect($review->fresh()->reply)->toHaveLength(600);
});

it('publishes the selected waiting reviews in one go and skips the rest', function () {
    $manager = reviewer();
    [$a, $b, $c] = Review::factory()->unpublished()->count(3)->create();
    $hiddenOne = Review::factory()->create();
    hidden($hiddenOne, $manager);

    $this->actingAs($manager)->post('/staff/reviews/publish', ['ids' => [$a->id, $b->id, $hiddenOne->id]])->assertRedirect();

    expect($a->fresh()->is_published)->toBeTrue()
        ->and($b->fresh()->is_published)->toBeTrue()
        ->and($c->fresh()->is_published)->toBeFalse()
        ->and($hiddenOne->fresh()->is_published)->toBeFalse()
        ->and(ActivityLog::query()->where('event', 'review.published')->count())->toBe(2);

    $this->post('/staff/reviews/publish', ['ids' => []])->assertSessionHasErrors('ids');
});

it('works out the rating summary for the last 30 days against the 30 before', function () {
    $make = fn (int $rating, string $when) => Review::factory()->rating($rating)->create(['created_at' => $when]);

    $make(5, '2026-10-20');
    $make(4, '2026-10-10');
    $make(3, '2026-10-01');
    $make(2, '2026-09-20');
    $make(1, '2026-09-01');
    $make(5, '2026-08-01');

    $summary = ReviewSummary::build();

    expect($summary['average'])->toBe(4.0)
        ->and($summary['count'])->toBe(3)
        ->and($summary['previous_average'])->toBe(1.5)
        ->and($summary['previous_count'])->toBe(2)
        ->and($summary['change'])->toBe(2.5)
        ->and($summary['stars'])->toBe([5 => 1, 4 => 1, 3 => 1, 2 => 0, 1 => 0]);
});

it('leaves hidden reviews out of the summary', function () {
    $manager = reviewer();
    Review::factory()->rating(5)->create(['created_at' => '2026-10-20']);
    $bad = Review::factory()->rating(1)->create(['created_at' => '2026-10-21']);
    hidden($bad, $manager);

    expect(ReviewSummary::build())->toMatchArray(['average' => 5.0, 'count' => 1]);
});

it('lists the words that recur in low ratings, ignoring filler', function () {
    expect(ReviewSummary::themes([
        'The delivery was cold and very late',
        'Cold naan, and the delivery took ages',
        'Late again, but the curry was lovely',
    ]))->toBe([
        ['word' => 'cold', 'count' => 2],
        ['word' => 'delivery', 'count' => 2],
        ['word' => 'late', 'count' => 2],
    ]);

    $good = Review::factory()->rating(5)->create(['body' => 'Cold drinks, lovely staff', 'created_at' => '2026-10-20']);
    Review::factory()->rating(2)->create(['body' => 'Slow service', 'created_at' => '2026-10-20']);
    Review::factory()->rating(1)->create(['body' => 'Slow and rude', 'created_at' => '2026-10-21']);

    expect(ReviewSummary::build()['themes'])->toBe([['word' => 'slow', 'count' => 2]]);
    expect($good->rating)->toBe(5);
});

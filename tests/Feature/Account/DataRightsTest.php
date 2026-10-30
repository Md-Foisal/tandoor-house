<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Address;
use App\Models\Cart;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\MenuSeeder;

beforeEach(fn () => $this->seed(MenuSeeder::class));

function exported($response): array
{
    return json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
}

it('sends guests to sign in for the export', function () {
    $this->get('/account/data-export')->assertRedirect('/login');
});

it('exports only the signed-in customer\'s own data without internal fields', function () {
    $me = User::factory()->create(['name' => 'Priya Nair', 'phone' => '07700900123', 'role' => Role::Customer]);
    $other = User::factory()->create(['name' => 'Someone Else', 'role' => Role::Customer]);
    Address::factory()->create(['user_id' => $me->id, 'line1' => '4 Mill Lane']);
    Address::factory()->create(['user_id' => $other->id, 'line1' => '99 Secret Road']);
    $order = Order::factory()->withLines(2)->forCustomer($me)->create(['notes' => 'No nuts']);
    Order::factory()->withLines()->forCustomer($other)->create();
    Reservation::factory()->create(['user_id' => $me->id, 'notes' => 'STAFF ONLY NOTE']);
    Review::factory()->create(['user_id' => $me->id, 'body' => 'Lovely']);
    LoyaltyTransaction::create(['user_id' => $me->id, 'points' => 10, 'kind' => 'earned', 'reason' => 'Order']);

    $response = $this->actingAs($me)->get('/account/data-export');
    $response->assertOk()->assertDownload();
    $raw = $response->streamedContent();
    $data = json_decode($raw, true);

    expect($data['profile']['email'])->toBe($me->email)
        ->and($data['addresses'])->toHaveCount(1)
        ->and($data['orders'])->toHaveCount(1)
        ->and($data['orders'][0]['number'])->toBe($order->number)
        ->and($data['orders'][0]['lines'])->toHaveCount(2)
        ->and($data['bookings'])->toHaveCount(1)
        ->and($data['reviews'])->toHaveCount(1)
        ->and($data['loyalty_transactions'])->toHaveCount(1)
        ->and($raw)->not->toContain('Secret Road')
        ->not->toContain($other->email)
        ->not->toContain('STAFF ONLY NOTE')
        ->not->toContain($me->password)
        ->not->toContain('remember_token');
});

it('rate limits the export', function () {
    $me = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->actingAs($me)->get('/account/data-export')->assertOk()->streamedContent();
    }

    $this->actingAs($me)->get('/account/data-export')->assertTooManyRequests();
});

it('logs a marketing opt-in and opt-out and defaults to off', function () {
    $me = User::factory()->create();
    expect($me->fresh()->marketing_opt_in)->toBeFalse();

    $this->actingAs($me)->patch('/settings/profile', ['name' => $me->name, 'email' => $me->email, 'marketing_opt_in' => '1'])
        ->assertSessionHasNoErrors();
    expect($me->fresh()->marketing_opt_in)->toBeTrue()
        ->and(ActivityLog::where('event', 'marketing.opted_in')->count())->toBe(1);

    $this->actingAs($me)->patch('/settings/profile', ['name' => $me->name, 'email' => $me->email, 'marketing_opt_in' => '0']);
    expect($me->fresh()->marketing_opt_in)->toBeFalse()
        ->and(ActivityLog::where('event', 'marketing.opted_out')->count())->toBe(1);

    $this->actingAs($me)->patch('/settings/profile', ['name' => 'New Name', 'email' => $me->email]);
    expect(ActivityLog::where('event', 'like', 'marketing.%')->count())->toBe(2);
});

it('deletes the account but keeps orders and bookings with identity stripped', function () {
    $me = User::factory()->create(['role' => Role::Customer]);
    Address::factory()->create(['user_id' => $me->id]);
    Cart::create(['user_id' => $me->id, 'lines' => []]);
    $order = Order::factory()->withLines(2)->forCustomer($me)->create(['notes' => 'Ring twice']);
    $total = $order->fresh()->total;
    $booking = Reservation::factory()->create(['user_id' => $me->id, 'name' => 'Priya', 'email' => 'p@example.com']);
    $review = Review::factory()->create(['user_id' => $me->id, 'author_name' => 'Priya N.', 'author_area' => 'Bow']);

    $this->actingAs($me)->delete('/settings/profile', ['password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect('/');

    expect(User::find($me->id))->toBeNull()
        ->and(Address::where('user_id', $me->id)->exists())->toBeFalse()
        ->and(Cart::where('user_id', $me->id)->exists())->toBeFalse();

    $order = $order->fresh();
    expect($order->user_id)->toBeNull()
        ->and($order->total)->toBe($total)
        ->and($order->lines)->toHaveCount(2)
        ->and($order->contact['email'])->toBe('')
        ->and($order->contact['name'])->not->toContain($me->name)
        ->and($order->address)->toBeNull()
        ->and($order->notes)->toBeNull();

    $booking = $booking->fresh();
    expect($booking->user_id)->toBeNull()->and($booking->email)->toBe('')->and($booking->name)->not->toBe('Priya');
    $review = $review->fresh();
    expect($review->user_id)->toBeNull()->and($review->author_area)->toBeNull()->and($review->author_name)->not->toContain('Priya');
    expect(ActivityLog::where('event', 'account.erased')->first()->user_id)->toBeNull();
});

it('stops the last owner deleting their account', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);

    $this->actingAs($owner)->from('/settings/profile')->delete('/settings/profile', ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect($owner->fresh())->not->toBeNull();
});

it('lets an owner delete their account when another owner exists', function () {
    $owner = User::factory()->create(['role' => Role::Owner]);
    User::factory()->create(['role' => Role::Owner]);

    $this->actingAs($owner)->delete('/settings/profile', ['password' => 'password'])->assertRedirect('/');

    expect(User::find($owner->id))->toBeNull();
});

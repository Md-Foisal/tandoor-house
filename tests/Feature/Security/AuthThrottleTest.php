<?php

use App\Enums\Role;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * Sends $limit requests that must pass, then one that must be refused.
 */
function throttlesAfter(int $limit, Closure $send): void
{
    foreach (range(1, $limit) as $attempt) {
        expect($send($attempt)->getStatusCode())->not->toBe(429, "request {$attempt} of {$limit} was throttled early");
    }

    $send($limit + 1)->assertStatus(429);
}

beforeEach(fn () => Notification::fake());

it('throttles registration at five a minute per IP', function () {
    throttlesAfter(5, fn ($n) => $this->postJson('/register', ['email' => "new{$n}@example.test"]));
});

it('throttles reset-link requests per email and IP', function () {
    throttlesAfter(5, fn () => $this->postJson('/forgot-password', ['email' => 'someone@example.test']));

    expect($this->postJson('/forgot-password', ['email' => 'other@example.test'])->getStatusCode())->not->toBe(429);
});

it('caps reset-link requests from one IP across many emails', function () {
    throttlesAfter(30, fn ($n) => $this->postJson('/forgot-password', ['email' => "spray{$n}@example.test"]));
});

it('throttles password resets per email and IP', function () {
    throttlesAfter(5, fn () => $this->postJson('/reset-password', ['token' => 'nope', 'email' => 'someone@example.test', 'password' => 'x', 'password_confirmation' => 'x']));
});

it('throttles password confirmation for a signed-in user', function () {
    $this->actingAs(User::factory()->role(Role::Manager)->create());

    throttlesAfter(5, fn () => $this->postJson('/user/confirm-password', ['password' => 'wrong-password']));
});

it('caps one IP across fresh sessions at a table', function () {
    $table = DiningTable::factory()->create();

    // No cookie is sent back, so every request is a new session: only the IP cap holds.
    throttlesAfter(300, fn () => $this->postJson("/t/{$table->qr_token}/requests", []));
});

it('caps one IP across fresh sessions at checkout', function () {
    throttlesAfter(120, fn () => $this->postJson('/checkout/postcode', ['postcode' => 'SW1A 1AA']));
});

it('caps one IP across fresh sessions on the basket', function () {
    throttlesAfter(600, fn () => $this->postJson('/basket/lines', []));
});

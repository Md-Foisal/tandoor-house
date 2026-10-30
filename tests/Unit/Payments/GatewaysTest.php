<?php

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Payments\AttemptStatus;
use App\Payments\Exceptions\GatewayNotConfigured;
use App\Payments\Gateways\FakeGateway;
use App\Payments\Gateways\StripeGateway;
use App\Payments\Gateways\VenueGateway;
use App\Payments\Payments;
use Tests\TestCase;

uses(TestCase::class);

it('refuses a live stripe key', function () {
    config(['payments.stripe.secret' => 'sk_live_abc']);

    new StripeGateway;
})->throws(GatewayNotConfigured::class);

it('refuses to build stripe without a secret', function () {
    config(['payments.stripe.secret' => null]);

    new StripeGateway;
})->throws(GatewayNotConfigured::class);

it('uses the fake gateway for cards and the venue for cash by default', function () {
    $payments = app(Payments::class);

    expect($payments->for(PaymentMethod::Card))->toBeInstanceOf(FakeGateway::class)
        ->and($payments->for(PaymentMethod::ApplePay))->toBeInstanceOf(FakeGateway::class)
        ->and($payments->for(PaymentMethod::Cash))->toBeInstanceOf(VenueGateway::class)
        ->and($payments->for(PaymentMethod::PayAtVenue))->toBeInstanceOf(VenueGateway::class);
});

it('makes deterministic fake references and declines 51p', function (int $amount, AttemptStatus $status) {
    $order = (new Order)->forceFill(['id' => 5, 'number' => 'TH-1']);
    $gateway = new FakeGateway;

    $one = $gateway->start($order, $amount, PaymentMethod::Card, ['confirm' => true]);
    $two = $gateway->start($order, $amount, PaymentMethod::Card, ['confirm' => true]);

    expect($one->status)->toBe($status)
        ->and($one->reference)->toBe($two->reference)
        ->and($one->reference)->toStartWith('fake_');
})->with([
    'ordinary amount' => [2450, AttemptStatus::Succeeded],
    'ends in 51p' => [1051, AttemptStatus::Failed],
    'ends in 15p' => [1015, AttemptStatus::Succeeded],
]);

it('refuses to run the fake gateway in production', function () {
    app()->detectEnvironment(fn () => 'production');

    new FakeGateway;
})->throws(GatewayNotConfigured::class);

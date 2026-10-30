<?php

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\ReservationUpdated;
use App\Listeners\NotifyCustomerOfBooking;
use App\Listeners\NotifyCustomerOfOrder;
use App\Models\Order;
use App\Models\Reservation;
use App\Notifications\Customer\BookingNotice;
use App\Notifications\Customer\OrderUpdate;
use App\Notifications\Sms\SmsSender;
use App\Support\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function orderStatusChanged(Order $order, OrderStatus $to): void
{
    app(NotifyCustomerOfOrder::class)->handleOrderStatusChanged(new OrderStatusChanged($order, null, $to));
}

it('notifies the customer for each worthwhile status', function (OrderStatus $to, bool $sent) {
    Notification::fake();
    $order = Order::factory()->pickup()->create();

    orderStatusChanged($order, $to);

    $sent ? Notification::assertSentOnDemand(OrderUpdate::class) : Notification::assertNothingSent();
})->with([
    'accepted' => [OrderStatus::Accepted, true],
    'ready' => [OrderStatus::Ready, true],
    'rejected' => [OrderStatus::Rejected, true],
    'cancelled' => [OrderStatus::Cancelled, true],
    'cooking' => [OrderStatus::Cooking, false],
    'collected' => [OrderStatus::Collected, false],
    'completed' => [OrderStatus::Completed, false],
]);

it('tells a delivery customer when the rider leaves and arrives, but not when it is ready', function () {
    Notification::fake();
    $order = Order::factory()->delivery()->create();

    orderStatusChanged($order, OrderStatus::Ready);
    Notification::assertNothingSent();

    orderStatusChanged($order, OrderStatus::OutForDelivery);
    orderStatusChanged($order, OrderStatus::Delivered);
    Notification::assertSentOnDemandTimes(OrderUpdate::class, 2);
});

it('confirms a new order once and leaves dine-in diners alone', function () {
    Notification::fake();
    $order = Order::factory()->pickup()->create();

    app(NotifyCustomerOfOrder::class)->handleOrderPlaced(new OrderPlaced($order));
    app(NotifyCustomerOfOrder::class)->handleOrderPlaced(new OrderPlaced($order));
    Notification::assertSentOnDemandTimes(OrderUpdate::class, 1);

    $table = Order::factory()->dineIn()->create();
    app(NotifyCustomerOfOrder::class)->handleOrderPlaced(new OrderPlaced($table));
    Notification::assertSentOnDemandTimes(OrderUpdate::class, 1);
});

it('routes to the order contact details', function () {
    Notification::fake();
    $order = Order::factory()->pickup()->create(['contact' => ['name' => 'Asha Rao', 'email' => 'asha@example.test', 'phone' => '07700 900123']]);

    orderStatusChanged($order, OrderStatus::Accepted);

    Notification::assertSentOnDemand(OrderUpdate::class, fn ($n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'asha@example.test'
        && $to->routes['sms'] === '07700 900123'
        && $channels === ['mail', 'sms']);
});

it('drops sms when the owner switches it off', function () {
    Notification::fake();
    app(Settings::class)->set('notification_channels', ['mail' => true, 'sms' => false, 'whatsapp' => false]);
    $order = Order::factory()->pickup()->create();

    orderStatusChanged($order, OrderStatus::Accepted);

    Notification::assertSentOnDemand(OrderUpdate::class, fn ($n, array $channels) => $channels === ['mail']);
});

it('renders the order email and text', function () {
    $order = Order::factory()->pickup()->create([
        'number' => 'TH-912345',
        'contact' => ['name' => 'Asha Rao', 'email' => 'asha@example.test', 'phone' => '07700 900123'],
        'estimated_ready_at' => now('Europe/London')->setTime(19, 5),
    ]);
    $notification = new OrderUpdate($order, OrderStatus::Accepted);

    $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

    expect($html)->toContain('Hello Asha')
        ->toContain('TH-912345')
        ->toContain('19:05')
        ->toContain(route('orders.show', $order))
        ->toContain(config('restaurant.contact.address.line1'));

    expect($notification->toSms(new AnonymousNotifiable)->text)
        ->toContain('TH-912345')
        ->toContain('19:05')
        ->toContain(route('orders.show', $order));
});

it('includes the reason when an order is rejected', function () {
    $order = Order::factory()->pickup()->create(['rejection_reason' => 'We have run out of lamb']);

    expect((new OrderUpdate($order, OrderStatus::Rejected))->text())->toContain('We have run out of lamb');
});

it('confirms a booking with the details and a calendar file', function () {
    Notification::fake();
    $reservation = Reservation::factory()->create(['party_size' => 4]);

    app(NotifyCustomerOfBooking::class)->handle(new ReservationUpdated($reservation));
    app(NotifyCustomerOfBooking::class)->handle(new ReservationUpdated($reservation));

    Notification::assertSentOnDemandTimes(BookingNotice::class, 1);
    Notification::assertSentOnDemand(BookingNotice::class, fn ($n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === $reservation->email);
});

it('renders the booking email in London time with an ics attachment', function () {
    $reservation = Reservation::factory()->create([
        'party_size' => 4,
        'starts_at' => '2026-07-10 18:30:00',
        'ends_at' => '2026-07-10 20:15:00',
    ]);
    $notification = new BookingNotice($reservation, ReservationStatus::Confirmed);
    $mail = $notification->toMail(new AnonymousNotifiable);

    $html = (string) $mail->render();

    expect($html)->toContain('18:30')
        ->toContain('Table for 4')
        ->toContain($reservation->reference)
        ->toContain(route('book.show', $reservation));
    expect($mail->rawAttachments[0]['data'])->toContain('BEGIN:VEVENT')->toContain('DTSTART:20260710T173000Z');
    expect($notification->text())->toContain('18:30');
});

it('sends a cancellation without a calendar file', function () {
    Notification::fake();
    $reservation = Reservation::factory()->create(['status' => ReservationStatus::Cancelled]);

    app(NotifyCustomerOfBooking::class)->handle(new ReservationUpdated($reservation));

    Notification::assertSentOnDemand(BookingNotice::class, function (BookingNotice $n) {
        return $n->toMail(new AnonymousNotifiable)->rawAttachments === [];
    });
});

it('says nothing for other booking statuses', function (ReservationStatus $status) {
    Notification::fake();
    $reservation = Reservation::factory()->create(['status' => $status]);

    app(NotifyCustomerOfBooking::class)->handle(new ReservationUpdated($reservation));

    Notification::assertNothingSent();
})->with([ReservationStatus::PendingDeposit, ReservationStatus::Seated, ReservationStatus::NoShow]);

it('runs through the event system and the real sms channel', function () {
    $sender = new class implements SmsSender
    {
        public array $sent = [];

        public function send(string $to, string $text): bool
        {
            $this->sent[] = [$to, $text];

            return true;
        }
    };
    app()->instance(SmsSender::class, $sender);
    $order = Order::factory()->pickup()->create(['contact' => ['name' => 'Asha Rao', 'email' => 'asha@example.test', 'phone' => '07700 900123']]);

    event(new OrderStatusChanged($order, OrderStatus::Placed, OrderStatus::Accepted));

    expect($sender->sent)->toHaveCount(1)
        ->and($sender->sent[0][0])->toBe('+447700900123')
        ->and($sender->sent[0][1])->toContain($order->number);
});

it('escapes markdown in customer-supplied names and reasons in emails', function () {
    $order = Order::factory()->pickup()->create([
        'contact' => ['name' => '[click](https://evil.test)', 'email' => 'a@example.test', 'phone' => '07700 900123'],
        'rejection_reason' => '[refund](https://evil.test/refund)',
    ]);

    $html = (string) (new OrderUpdate($order, OrderStatus::Rejected))->toMail(new AnonymousNotifiable)->render();
    expect($html)->not->toContain('href="https://evil.test');

    $reservation = Reservation::factory()->create(['name' => '[click](https://evil.test)']);
    $html = (string) (new BookingNotice($reservation, ReservationStatus::Confirmed))->toMail(new AnonymousNotifiable)->render();
    expect($html)->not->toContain('href="https://evil.test');
});

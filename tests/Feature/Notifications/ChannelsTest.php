<?php

use App\Models\Setting;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\SmsMessage;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\NotificationChannels;
use App\Notifications\PhoneNumber;
use App\Notifications\Sms\LogSmsSender;
use App\Notifications\Sms\SmsSender;
use App\Notifications\WhatsApp\NullWhatsAppSender;
use App\Notifications\WhatsApp\WhatsAppSender;
use App\Support\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class RecordingSmsSender implements SmsSender
{
    /** @var list<array{string, string}> */
    public array $sent = [];

    public function send(string $to, string $text): bool
    {
        $this->sent[] = [$to, $text];

        return true;
    }
}

class PingNotification extends Notification
{
    public function __construct(private readonly string $text = 'Your order is ready') {}

    public function via(object $notifiable): array
    {
        return ['sms', 'whatsapp'];
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return SmsMessage::make($this->text);
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return WhatsAppMessage::make($this->text);
    }
}

it('sends texts through the bound sender to the on-demand number', function () {
    $sender = new RecordingSmsSender;
    app()->instance(SmsSender::class, $sender);

    NotificationFacade::route('sms', '07700 900123')->notifyNow(new PingNotification);

    expect($sender->sent)->toBe([['+447700900123', 'Your order is ready']]);
});

it('routes to a user through their phone number', function () {
    $sender = new RecordingSmsSender;
    app()->instance(SmsSender::class, $sender);

    $user = User::factory()->create(['phone' => '+44 7700 900456']);
    app(SmsChannel::class)->send($user, new PingNotification);

    expect($sender->sent[0][0])->toBe('+447700900456');
});

it('skips numbers it cannot route', function () {
    $sender = new RecordingSmsSender;
    app()->instance(SmsSender::class, $sender);

    $sent = app(SmsChannel::class)->send((new AnonymousNotifiable)->route('sms', '01632 960000'), new PingNotification);

    expect($sent)->toBeFalse()->and($sender->sent)->toBe([]);
});

it('fits long texts into three segments', function () {
    $message = SmsMessage::make(str_repeat('a', 700));

    expect(SmsMessage::make('hi')->segments())->toBe(1)
        ->and(SmsMessage::make(str_repeat('a', 161))->segments())->toBe(2)
        ->and(mb_strlen($message->fitted(3)))->toBeLessThanOrEqual(459)
        ->and(SmsMessage::make(str_repeat('a', 459))->fitted(3))->toHaveLength(459);
});

it('normalises phone numbers to E.164', function (?string $in, ?string $out) {
    expect(PhoneNumber::e164($in))->toBe($out);
})->with([
    ['07700 900123', '+447700900123'],
    ['+44 (0)7700-900123', '+447700900123'],
    ['0044 7700 900123', '+447700900123'],
    ['+353 87 123 4567', '+353871234567'],
    ['020 7946 0000', null],
    ['', null],
    [null, null],
]);

it('writes texts to the sms log by default', function () {
    expect(app(SmsSender::class))->toBeInstanceOf(LogSmsSender::class);

    Log::shouldReceive('channel')->with('sms')->once()->andReturnSelf();
    Log::shouldReceive('info')->once()->with('sms', ['to' => '+447700900123', 'text' => 'Hello']);

    expect(app(SmsSender::class)->send('+447700900123', 'Hello'))->toBeTrue();
});

it('keeps whatsapp off with the null driver', function () {
    expect(app(WhatsAppSender::class))->toBeInstanceOf(NullWhatsAppSender::class)
        ->and(app(WhatsAppSender::class)->send('+447700900123', 'Hello'))->toBeFalse()
        ->and(app(WhatsAppChannel::class)->send((new AnonymousNotifiable)->route('whatsapp', '07700 900123'), new PingNotification))->toBeFalse();
});

it('queues on-demand sms and whatsapp through the notification fake', function () {
    NotificationFacade::fake();

    NotificationFacade::route('mail', 'guest@example.com')->route('sms', '07700 900123')->notify(new PingNotification);

    NotificationFacade::assertSentOnDemand(PingNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['sms'] === '07700 900123'
        && $channels === ['sms', 'whatsapp']);
});

it('follows the restaurant notification settings', function () {
    expect(NotificationChannels::for('order'))->toBe(['mail', 'sms']);

    Setting::updateOrCreate(['key' => 'notification_channels'], ['value' => ['mail' => true, 'sms' => false, 'whatsapp' => true]]);
    app(Settings::class)->flush();

    expect(NotificationChannels::for('order'))->toBe(['mail']);

    config(['notify.whatsapp.driver' => 'cloud']);
    expect(NotificationChannels::for('order'))->toBe(['mail', 'whatsapp']);

    Setting::updateOrCreate(['key' => 'notification_channels'], ['value' => ['mail' => true, 'sms' => true]]);
    app(Settings::class)->flush();

    expect(NotificationChannels::for('review_request'))->toBe(['mail']);
});

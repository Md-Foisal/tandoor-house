<?php

use App\Services\OpeningHours;
use App\Support\Settings;
use Carbon\CarbonImmutable;

function hours(): OpeningHours
{
    return app(OpeningHours::class);
}

function london(string $time): CarbonImmutable
{
    return CarbonImmutable::parse($time, 'Europe/London');
}

it('is open during a service period', function () {
    $status = hours()->status(london('2026-10-27 19:00'));

    expect($status)->toBe(['isOpen' => true, 'until' => '22:30', 'opensAt' => null]);
});

it('is closed between lunch and dinner and says when it opens', function () {
    $status = hours()->status(london('2026-10-27 16:00'));

    expect($status['isOpen'])->toBeFalse()
        ->and($status['opensAt'])->toBe('at 17:30');
});

it('skips the closed Monday', function () {
    $status = hours()->status(london('2026-10-26 10:00'));

    expect($status['opensAt'])->toBe('tomorrow at 12:00');
});

it('closes on bank holidays', function () {
    app(Settings::class)->set('bank_holidays', ['2026-12-25']);

    expect(hours()->isOpen(london('2026-12-25 13:00')))->toBeFalse();
});

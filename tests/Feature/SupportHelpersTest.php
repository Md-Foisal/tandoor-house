<?php

use App\Models\Order;
use App\Support\CodeGenerator;
use App\Support\Money;

it('formats pence as pounds', function () {
    expect(Money::format(1250))->toBe('£12.50')
        ->and(Money::format(1200))->toBe('£12')
        ->and(Money::format(5))->toBe('£0.05');
});

it('generates codes without ambiguous characters', function () {
    $code = CodeGenerator::unique(Order::class, 'number', 'TH-', 5);

    expect($code)->toMatch('/^TH-[A-HJ-NP-Z2-9]{5}$/');
});

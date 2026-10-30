@php
    $money = fn (int $pence): string => ($pence < 0 ? '-' : '').'£'.number_format(abs($pence) / 100, 2);
    $address = $restaurant['contact']['address'];
    $method = __('staff_orders.payment.method.'.$order->payment_method->value);
    $placed = $order->placed_at->timezone(config('app.timezone'))->format('j M Y, H:i');
    $isPaid = $paid > 0;
@endphp
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('staff_orders.receipt.title', ['number' => $order->number]) }}</title>
    <style>
        @page { margin: 12px; }
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; line-height: 1.4; color: #1a1a1a; margin: 0; background: #fff; }
        .sheet { width: 100%; max-width: 320px; margin: 0 auto; padding: 8px; }
        h1 { font-size: 16px; margin: 0 0 2px; text-align: center; }
        .center { text-align: center; }
        .muted { color: #555; }
        .small { font-size: 9.5px; }
        hr { border: 0; border-top: 1px dashed #999; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 2px 0; }
        td.num { text-align: right; white-space: nowrap; padding-left: 8px; }
        td.qty { width: 22px; }
        .total td { font-size: 14px; font-weight: bold; padding-top: 4px; }
        .strong { font-weight: bold; }
        .actions { text-align: center; margin: 12px 0; }
        .actions a, .actions button { font: inherit; font-weight: bold; padding: 10px 16px; border: 1.5px solid #1a1a1a; background: #fff; color: #1a1a1a; border-radius: 6px; text-decoration: none; cursor: pointer; display: inline-block; margin: 0 4px; }
        @media print { .actions { display: none; } .sheet { max-width: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <h1>{{ $restaurant['name'] }}</h1>
    <p class="center muted small">
        {{ $address['line1'] }}, {{ $address['city'] }} {{ $address['postcode'] }}<br>
        {{ $restaurant['contact']['phone'] }}<br>
        {{ $restaurant['company']['legal_name'] }}<br>
        {{ __('staff_orders.receipt.vat_number', ['number' => $restaurant['company']['vat_number']]) }}
    </p>
    <hr>
    <table>
        <tr>
            <td class="strong">{{ __('staff_orders.receipt.order', ['number' => $order->number]) }}</td>
            <td class="num">{{ $placed }}</td>
        </tr>
        <tr>
            <td colspan="2" class="muted">
                @if ($table)
                    {{ __('staff_orders.receipt.table', ['label' => $table]) }}
                @else
                    {{ __('staff.channel.'.$order->channel->value) }}
                @endif
            </td>
        </tr>
    </table>
    <hr>
    <table>
        @foreach ($lines as $line)
            <tr>
                <td class="qty">{{ $line->quantity }}</td>
                <td>
                    {{ $line->name }}@if ($line->variant_name) ({{ $line->variant_name }})@endif
                    @foreach ($line->modifiers as $modifier)
                        <br><span class="muted small">+ {{ $modifier['name'] }}</span>
                    @endforeach
                </td>
                <td class="num">{{ $money($line->unit_price * $line->quantity) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>{{ __('staff_orders.totals.subtotal') }}</td><td class="num">{{ $money($order->subtotal) }}</td></tr>
        @if ($order->discount > 0)
            <tr><td>{{ __('staff_orders.totals.discount') }}</td><td class="num">{{ $money(-$order->discount) }}</td></tr>
        @endif
        @if ($order->delivery_fee > 0)
            <tr><td>{{ __('staff_orders.totals.delivery_fee') }}</td><td class="num">{{ $money($order->delivery_fee) }}</td></tr>
        @endif
        @if ($order->service_charge > 0)
            <tr>
                <td>{{ __('staff_orders.totals.service_charge') }}<br><span class="muted small">{{ __('staff_orders.receipt.optional') }}</span></td>
                <td class="num">{{ $money($order->service_charge) }}</td>
            </tr>
        @endif
        @if ($order->tip > 0)
            <tr><td>{{ __('staff_orders.totals.tip') }}</td><td class="num">{{ $money($order->tip) }}</td></tr>
        @endif
        <tr class="total"><td>{{ __('staff_orders.totals.total') }}</td><td class="num">{{ $money($order->total) }}</td></tr>
    </table>
    <hr>
    <table>
        <tr><td colspan="2" class="strong">{{ __('staff_orders.receipt.vat_included') }}</td></tr>
        @foreach ($vat as $row)
            <tr>
                <td class="muted">{{ __('staff_orders.receipt.vat_line', ['rate' => rtrim(rtrim(number_format($row['rate'] / 100, 2), '0'), '.'), 'gross' => $money($row['gross'])]) }}</td>
                <td class="num">{{ $money($row['vat']) }}</td>
            </tr>
        @endforeach
        <tr><td class="strong">{{ __('staff_orders.totals.vat_total') }}</td><td class="num strong">{{ $money($order->vat_total) }}</td></tr>
    </table>
    <hr>
    <table>
        @if ($isPaid)
            <tr><td>{{ __('staff_orders.receipt.paid_by', ['method' => $method]) }}</td><td class="num">{{ $money($paid) }}</td></tr>
            @if ($refunded > 0)
                <tr><td>{{ __('staff_orders.receipt.refunded') }}</td><td class="num">{{ $money(-$refunded) }}</td></tr>
                <tr><td class="strong">{{ __('staff_orders.receipt.net_paid') }}</td><td class="num strong">{{ $money($paid - $refunded) }}</td></tr>
            @endif
        @else
            <tr><td>{{ __('staff_orders.receipt.unpaid') }} ({{ $method }})</td><td class="num">{{ $money($order->total) }}</td></tr>
        @endif
    </table>
    @if ($footer)
        <hr>
        <p class="center muted small">{{ $footer }}</p>
    @endif
    @if ($printable)
        <div class="actions">
            <button type="button" onclick="window.print()">{{ __('staff_orders.receipt.print') }}</button>
            <a href="{{ route('staff.orders.show', $order->number) }}">{{ __('staff_orders.receipt.back') }}</a>
        </div>
    @endif
</div>
</body>
</html>

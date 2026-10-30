@php
    use App\Actions\Inventory\Quantity;
    use App\Support\Money;

    $unitLabel = ['g' => 'per kg', 'ml' => 'per litre', 'pc' => 'per 1,000'];
@endphp
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <title>{{ $order->reference }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1d1a17; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #6b645c; }
        .row { width: 100%; margin-bottom: 22px; }
        .row td { vertical-align: top; width: 50%; padding: 0; }
        .right { text-align: right; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #6b645c; border-bottom: 1px solid #1d1a17; padding: 6px 4px; }
        table.lines td { padding: 7px 4px; border-bottom: 1px solid #ddd6cc; }
        table.lines .num { text-align: right; }
        .total td { border-bottom: 0; border-top: 2px solid #1d1a17; font-weight: bold; font-size: 13px; padding-top: 10px; }
        .foot { margin-top: 28px; font-size: 10px; color: #6b645c; }
    </style>
</head>
<body>
    <table class="row">
        <tr>
            <td>
                <h1>Purchase order {{ $order->reference }}</h1>
                <div class="muted">{{ $order->created_at?->timezone(config('app.timezone'))->format('j F Y') }}</div>
            </td>
            <td class="right">
                <strong>{{ $restaurant['company']['legal_name'] }}</strong><br>
                {{ $restaurant['contact']['address']['line1'] }}<br>
                {{ $restaurant['contact']['address']['city'] }} {{ $restaurant['contact']['address']['postcode'] }}<br>
                {{ $restaurant['contact']['phone'] }} · {{ $restaurant['contact']['email'] }}
            </td>
        </tr>
    </table>

    <table class="row">
        <tr>
            <td>
                <div class="muted">Supplier</div>
                <strong>{{ $order->supplier->name }}</strong><br>
                @if ($order->supplier->contact_name) {{ $order->supplier->contact_name }}<br> @endif
                @if ($order->supplier->email) {{ $order->supplier->email }}<br> @endif
                @if ($order->supplier->phone) {{ $order->supplier->phone }} @endif
            </td>
            <td class="right">
                <div class="muted">Delivery</div>
                Within {{ $order->supplier->lead_days }} {{ $order->supplier->lead_days === 1 ? 'day' : 'days' }} of order<br>
                @if ($order->sent_at) <span class="muted">Sent {{ $order->sent_at->timezone(config('app.timezone'))->format('j F Y') }}</span> @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Quantity</th>
                <th class="num">Unit price</th>
                <th class="num">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->lines->sortBy(fn ($l) => $l->ingredient->name) as $line)
                <tr>
                    <td>{{ $line->ingredient->name }}</td>
                    <td class="num">{{ Quantity::format($line->quantity, $line->ingredient->unit) }}</td>
                    <td class="num">{{ Money::format($line->unit_cost) }} {{ $unitLabel[$line->ingredient->unit] ?? '' }}</td>
                    <td class="num">{{ Money::format((int) round($line->quantity * $line->unit_cost / 1000)) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">Total (excluding VAT)</td>
                <td class="num">{{ Money::format($order->total) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="foot">Please confirm this order and the delivery date by replying to {{ $restaurant['contact']['email'] }}. Quote {{ $order->reference }} on your delivery note.</p>
</body>
</html>

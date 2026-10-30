@php
    use App\Support\Money;
    use App\Support\MarkdownText;
    $m = $report['metrics'];
    $compare = $compare ?? false;
@endphp
## {{ __('automations.reports.headline') }}

@if ($m['orders']['value'] === 0)
{{ __('automations.reports.no_sales') }}
@else
@if ($compare)
| {{ __('automations.reports.measure') }} | {{ __('automations.reports.this_week') }} | {{ __('automations.reports.last_week') }} | {{ __('automations.reports.change') }} |
|:--|--:|--:|--:|
@foreach (['sales' => 'sales', 'orders' => 'orders', 'aov' => 'average_order', 'net_of_vat' => 'net_sales', 'refunds' => 'refunds', 'tips' => 'tips', 'service' => 'service_charge', 'vat' => 'vat'] as $key => $label)
| {{ __('automations.reports.'.$label) }} | {{ $key === 'orders' ? $m[$key]['value'] : Money::format($m[$key]['value']) }} | {{ $key === 'orders' ? $m[$key]['previous'] : Money::format($m[$key]['previous']) }} | {{ \App\Reports\Digest::change($m[$key]['change']) }} |
@endforeach
@else
| {{ __('automations.reports.measure') }} | {{ __('automations.reports.figure') }} |
|:--|--:|
| {{ __('automations.reports.sales') }} | {{ Money::format($m['sales']['value']) }} |
| {{ __('automations.reports.net_sales') }} | {{ Money::format($m['net_of_vat']['value']) }} |
| {{ __('automations.reports.gross_sales') }} | {{ Money::format($m['gross']['value']) }} |
| {{ __('automations.reports.orders') }} | {{ $m['orders']['value'] }} |
| {{ __('automations.reports.average_order') }} | {{ Money::format($m['aov']['value']) }} |
| {{ __('automations.reports.discounts') }} | {{ Money::format($m['discounts']['value']) }} |
| {{ __('automations.reports.refunds') }} | {{ Money::format($m['refunds']['value']) }} |
| {{ __('automations.reports.tips') }} | {{ Money::format($m['tips']['value']) }} |
| {{ __('automations.reports.service_charge') }} | {{ Money::format($m['service']['value']) }} |
| {{ __('automations.reports.vat') }} | {{ Money::format($m['vat']['value']) }} |
@endif

{{ __('automations.reports.sales_note') }}

{{ __('automations.reports.turned_away') }}: {{ $report['turned_away'] }}

## {{ __('automations.reports.by_channel') }}

| {{ __('automations.reports.channel') }} | {{ __('automations.reports.orders') }} | {{ __('automations.reports.sales') }} |
|:--|--:|--:|
@foreach ($report['channels'] as $row)
| {{ $row['label'] }} | {{ $row['orders'] }} | {{ Money::format($row['sales']) }} |
@endforeach

## {{ __('automations.reports.payment_mix') }}

| {{ __('automations.reports.method') }} | {{ __('automations.reports.orders') }} | {{ __('automations.reports.share') }} |
|:--|--:|--:|
@foreach ($report['payments'] as $row)
| {{ $row['label'] }} | {{ $row['orders'] }} | {{ rtrim(rtrim(number_format($row['share'], 1), '0'), '.') }}% |
@endforeach

## {{ __('automations.reports.top_dishes') }}

| {{ __('automations.reports.dish') }} | {{ __('automations.reports.sold') }} | {{ __('automations.reports.revenue') }} |
|:--|--:|--:|
@foreach ($report['top'] as $row)
| {{ MarkdownText::escape($row['name']) }} | {{ $row['quantity'] }} | {{ Money::format($row['revenue']) }} |
@endforeach
@endif

## {{ __('automations.reports.bookings') }}

@if ($report['bookings']['count'] === 0 && $report['bookings']['no_shows'] === 0)
{{ __('automations.reports.no_bookings') }}
@else
{{ __('automations.reports.bookings_line', ['bookings' => $report['bookings']['count'], 'covers' => $report['bookings']['covers']]) }}
@if ($report['bookings']['no_shows'] > 0)
{{ trans_choice('automations.reports.no_shows', $report['bookings']['no_shows'], ['count' => $report['bookings']['no_shows']]) }}
@endif
@endif

## {{ __('automations.reports.stock') }}

@if ($report['waste']['entries'] > 0)
{{ trans_choice('automations.reports.waste_line', $report['waste']['entries'], ['cost' => Money::format($report['waste']['cost']), 'entries' => $report['waste']['entries']]) }}
@else
{{ __('automations.reports.no_waste') }}
@endif

@if ($report['low_stock'] === [])
{{ __('automations.reports.stock_fine') }}
@else
**{{ __('automations.reports.low_stock') }}**

@foreach ($report['low_stock'] as $row)
- {{ __('automations.reports.low_stock_line', ['name' => MarkdownText::escape($row['name']), 'stock' => $row['stock'], 'unit' => MarkdownText::escape($row['unit']), 'threshold' => $row['threshold']]) }}
@endforeach
@endif

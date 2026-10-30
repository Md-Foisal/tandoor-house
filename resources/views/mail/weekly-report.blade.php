@php
    use App\Support\Money;
    use App\Support\MarkdownText;
@endphp
<x-mail::message>
# {{ __('automations.reports.weekly.title', ['restaurant' => config('restaurant.name')]) }}

{{ __('automations.reports.greeting', ['name' => $name]) }}

{{ __('automations.reports.weekly.intro', ['label' => $report['label']]) }}

{{ __('automations.reports.weekly.compared', ['period' => $report['previous_label']]) }}

@include('mail.report-body', ['compare' => true])

## {{ __('automations.reports.weekly.timing') }}

@if ($report['busiest_day'])
{{ __('automations.reports.weekly.busiest_day', ['day' => $report['busiest_day']['label'], 'sales' => Money::format($report['busiest_day']['sales']), 'orders' => $report['busiest_day']['orders']]) }}

@endif
@if ($report['busiest_hour'])
{{ __('automations.reports.weekly.busiest_hour', ['hour' => $report['busiest_hour']['label'], 'sales' => Money::format($report['busiest_hour']['sales']), 'orders' => $report['busiest_hour']['orders']]) }}

@endif
@if ($report['timed_orders'] > 0)
{{ __('automations.reports.weekly.prep', ['minutes' => rtrim(rtrim(number_format($report['prep_minutes'], 1), '0'), '.'), 'orders' => $report['timed_orders']]) }}
@else
{{ __('automations.reports.weekly.no_prep') }}
@endif

## {{ __('automations.reports.slow_dishes') }}

{{ __('automations.reports.slow_note') }}

| {{ __('automations.reports.dish') }} | {{ __('automations.reports.sold') }} | {{ __('automations.reports.revenue') }} |
|:--|--:|--:|
@foreach ($report['slow'] as $row)
| {{ MarkdownText::escape($row['name']) }} | {{ $row['quantity'] }} | {{ Money::format($row['revenue']) }} |
@endforeach

<x-mail::button :url="$url">
{{ __('automations.reports.view') }}
</x-mail::button>
</x-mail::message>

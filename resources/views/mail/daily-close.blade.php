<x-mail::message>
# {{ __('automations.reports.daily.title', ['date' => $report['label']]) }}

{{ __('automations.reports.greeting', ['name' => $name]) }}

{{ __('automations.reports.daily.intro', ['date' => $report['label']]) }}

@include('mail.report-body', ['compare' => false])

<x-mail::button :url="$url">
{{ __('automations.reports.view') }}
</x-mail::button>
</x-mail::message>

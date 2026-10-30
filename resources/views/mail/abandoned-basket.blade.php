<x-mail::message>
# {{ $headline }}

{{ __('notify.greeting', ['name' => $name]) }}

{{ $body }}

<x-mail::panel>
{{ trans_choice('automations.customers.abandoned_basket.items', $count, ['count' => $count]) }}
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('automations.customers.abandoned_basket.action') }}
</x-mail::button>
</x-mail::message>

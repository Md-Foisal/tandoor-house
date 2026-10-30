<x-mail::message>
# {{ $headline }}

{{ __('notify.greeting', ['name' => $name]) }}

{{ $body }}

<x-mail::button :url="$url">
{{ __('automations.customers.review_request.action') }}
</x-mail::button>

{{ __('notify.order.reference', ['number' => $order->number]) }}
</x-mail::message>

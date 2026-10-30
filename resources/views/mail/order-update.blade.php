<x-mail::message>
# {{ $headline }}

{{ __('notify.greeting', ['name' => $name]) }}

{{ $body }}

<x-mail::button :url="$url">
{{ __('notify.order.track') }}
</x-mail::button>

{{ __('notify.order.reference', ['number' => $order->number]) }}
</x-mail::message>

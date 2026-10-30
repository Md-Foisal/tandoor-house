<x-mail::message>
# {{ $headline }}

{{ __('notify.greeting', ['name' => $name]) }}

{{ $body }}

<x-mail::panel>
**{{ $when }}**
{{ trans_choice('notify.booking.party', $reservation->party_size, ['count' => $reservation->party_size]) }}
{{ __('notify.booking.reference', ['reference' => $reservation->reference]) }}
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('notify.booking.manage') }}
</x-mail::button>
</x-mail::message>

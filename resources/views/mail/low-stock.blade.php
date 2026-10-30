<x-mail::message>
# {{ __('automations.stock.mail.headline', ['ingredient' => $ingredient]) }}

{{ __('notify.greeting', ['name' => $name]) }}

{{ __('automations.stock.mail.body', ['ingredient' => $ingredient, 'stock' => $stock, 'threshold' => $threshold]) }}

@if ($order)
{{ __('automations.stock.mail.drafted', ['reference' => $order->reference]) }}
@else
{{ __('automations.stock.mail.no_supplier') }}
@endif

<x-mail::button :url="$url">
{{ $order ? __('automations.stock.mail.open_order') : __('automations.stock.mail.open_stock') }}
</x-mail::button>
</x-mail::message>

<x-mail::message>
# {{ __('automations.stock.supplier_mail.headline', ['reference' => $order->reference]) }}

{{ __('notify.greeting', ['name' => $contact]) }}

{{ __('automations.stock.supplier_mail.intro', ['restaurant' => $restaurant['name'], 'days' => $order->supplier->lead_days]) }}

<x-mail::table>
| {{ __('automations.stock.supplier_mail.item') }} | {{ __('automations.stock.supplier_mail.quantity') }} |
| :-- | --: |
@foreach ($order->lines as $line)
| {{ $line->ingredient->name }} | {{ \App\Actions\Inventory\Quantity::format($line->quantity, $line->ingredient->unit) }} |
@endforeach
</x-mail::table>

{{ __('automations.stock.supplier_mail.total', ['total' => $total]) }}

{{ __('automations.stock.supplier_mail.reply', ['email' => $restaurant['contact']['email'], 'phone' => $restaurant['contact']['phone']]) }}
</x-mail::message>

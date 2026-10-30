<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
{{ Illuminate\Mail\Markdown::parse($slot) }}
<p>{{ config('restaurant.name') }}, {{ config('restaurant.contact.address.line1') }}, {{ config('restaurant.contact.address.city') }} {{ config('restaurant.contact.address.postcode') }}<br>{{ config('restaurant.contact.phone') }}</p>
</td>
</tr>
</table>
</td>
</tr>

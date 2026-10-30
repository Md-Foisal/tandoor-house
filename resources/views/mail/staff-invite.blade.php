<x-mail::message>
# {{ __('staff_team.invite.headline', ['restaurant' => $restaurant]) }}

{{ __('notify.greeting', ['name' => $name]) }}

{{ __('staff_team.invite.body', ['invitedBy' => $invitedBy, 'restaurant' => $restaurant, 'role' => $role]) }}

<x-mail::button :url="$url">
{{ __('staff_team.invite.button') }}
</x-mail::button>

{{ __('staff_team.invite.expiry', ['days' => $days]) }}
</x-mail::message>

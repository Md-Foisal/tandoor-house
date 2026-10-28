<?php

namespace App\Http\Requests\Account;

class UpdateAddressRequest extends AddressRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('address')) ?? false;
    }
}

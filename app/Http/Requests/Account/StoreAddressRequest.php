<?php

namespace App\Http\Requests\Account;

use App\Http\Controllers\Account\AddressController;
use Illuminate\Validation\Validator;

class StoreAddressRequest extends AddressRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->user()->addresses()->count() >= AddressController::MAX) {
                    $validator->errors()->add('label', __('addresses.errors.limit', ['max' => AddressController::MAX]));
                }
            },
        ];
    }
}

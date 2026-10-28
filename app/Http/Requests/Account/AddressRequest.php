<?php

namespace App\Http\Requests\Account;

use App\Actions\UkFormats;
use Illuminate\Foundation\Http\FormRequest;

abstract class AddressRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $postcode = $this->input('postcode');

        if (is_string($postcode)) {
            $this->merge(['postcode' => UkFormats::postcode($postcode) ?? trim($postcode)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:120'],
            'line2' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'postcode' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (UkFormats::postcode((string) $value) !== $value) {
                    $fail(__('addresses.errors.postcode'));
                }
            }],
            'instructions' => ['nullable', 'string', 'max:200'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => __('addresses.errors.label'),
            'line1.required' => __('addresses.errors.line1'),
            'city.required' => __('addresses.errors.city'),
            'postcode.required' => __('addresses.errors.postcode'),
            'instructions.max' => __('addresses.errors.instructions'),
        ];
    }
}

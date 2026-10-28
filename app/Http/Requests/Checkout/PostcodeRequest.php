<?php

namespace App\Http\Requests\Checkout;

use App\Actions\UkFormats;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PostcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $postcode = $this->input('postcode');

        $this->merge([
            'postcode' => is_string($postcode) ? (UkFormats::postcode($postcode) ?? $postcode) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'postcode' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (UkFormats::postcode((string) $value) === null) {
                    $fail(__('checkout.errors.postcode'));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['postcode.required' => __('checkout.errors.postcode')];
    }
}

<?php

namespace App\Http\Requests\Table;

use App\Services\CartService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'age_confirmed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (app(CartService::class)->summary()['has_alcohol'] && ! $this->boolean('age_confirmed')) {
                $validator->errors()->add('age_confirmed', __('table.errors.age'));
            }
        }];
    }
}

<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('order')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $area = $this->input('author_area');

        $this->merge(['author_area' => is_string($area) && trim($area) !== '' ? trim($area) : null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:20', 'max:1000'],
            'author_area' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => __('reviews.errors.rating'),
            'rating.between' => __('reviews.errors.rating'),
            'rating.integer' => __('reviews.errors.rating'),
            'body.required' => __('reviews.errors.body_short'),
            'body.min' => __('reviews.errors.body_short'),
            'body.max' => __('reviews.errors.body_long'),
            'author_area.max' => __('reviews.errors.area'),
        ];
    }
}

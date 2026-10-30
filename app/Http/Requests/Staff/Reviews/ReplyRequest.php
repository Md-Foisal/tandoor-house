<?php

namespace App\Http\Requests\Staff\Reviews;

use Illuminate\Foundation\Http\FormRequest;

class ReplyRequest extends FormRequest
{
    public const MAX = 600;

    public function authorize(): bool
    {
        return $this->user()?->can('staff.reviews') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reply'))) {
            $this->merge(['reply' => trim($this->input('reply'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reply' => ['required', 'string', 'max:'.self::MAX]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reply.required' => __('staff_reviews.errors.reply_required'),
            'reply.max' => __('staff_reviews.errors.reply_max', ['max' => self::MAX]),
        ];
    }
}

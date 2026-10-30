<?php

namespace App\Http\Requests\Staff\Reviews;

use App\Http\Controllers\Staff\Reviews\ReviewQueue;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HideReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('moderate', Review::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(ReviewQueue::HIDE_REASONS)],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}

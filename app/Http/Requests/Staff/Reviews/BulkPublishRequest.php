<?php

namespace App\Http\Requests\Staff\Reviews;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class BulkPublishRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ];
    }
}

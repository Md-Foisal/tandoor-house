<?php

namespace App\Http\Requests\Staff\Floor;

use Illuminate\Foundation\Http\FormRequest;

class UnmergeTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('unmerge', $this->route('table')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

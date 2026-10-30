<?php

namespace App\Http\Requests\Staff\Activity;

use App\Models\ActivityLog;
use Illuminate\Foundation\Http\FormRequest;

class ActivityFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ActivityLog::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'family' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'person' => ['nullable', 'string', 'regex:/^(automation|\d+)$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{family: string|null, person: string|null, from: string|null, to: string|null, q: string|null}
     */
    public function filters(): array
    {
        $q = trim((string) $this->validated('q', ''));

        return [
            'family' => $this->validated('family') ?: null,
            'person' => $this->validated('person') ?: null,
            'from' => $this->validated('from') ?: null,
            'to' => $this->validated('to') ?: null,
            'q' => $q !== '' ? $q : null,
        ];
    }
}

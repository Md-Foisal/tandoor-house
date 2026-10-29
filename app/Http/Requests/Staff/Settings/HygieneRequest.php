<?php

namespace App\Http\Requests\Staff\Settings;

use Illuminate\Validation\Rule;

class HygieneRequest extends SettingsRequest
{
    public const AWAITING = 'Awaiting inspection';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', Rule::in([0, 1, 2, 3, 4, 5, '0', '1', '2', '3', '4', '5', self::AWAITING])],
            'opened_year' => ['required', 'integer', 'min:1900', 'max:'.now()->year],
        ];
    }

    public function rating(): int|string
    {
        $rating = $this->validated('rating');

        return $rating === self::AWAITING ? self::AWAITING : (int) $rating;
    }
}

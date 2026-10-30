<?php

namespace App\Http\Requests\Staff\Settings;

class HolidaysRequest extends SettingsRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'dates' => ['present', 'array', 'max:200'],
            'dates.*' => ['required', 'date_format:Y-m-d', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function dates(): array
    {
        $dates = array_map('strval', (array) $this->validated('dates'));
        sort($dates);

        return $dates;
    }
}

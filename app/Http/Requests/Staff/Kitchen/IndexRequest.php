<?php

namespace App\Http\Requests\Staff\Kitchen;

use App\Enums\Station;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.kitchen') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['station' => ['nullable', 'string']];
    }

    /**
     * The chosen station, or "all" when the query is missing or unknown.
     */
    public function station(): string
    {
        $station = $this->query('station');

        return is_string($station) && Station::tryFrom($station) !== null ? $station : 'all';
    }
}

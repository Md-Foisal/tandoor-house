<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class DataExportRequest extends FormRequest
{
    /** The export is always the signed-in person's own data; there is no id to tamper with. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}

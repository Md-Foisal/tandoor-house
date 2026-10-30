<?php

namespace App\Http\Requests\Staff\Settings;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

abstract class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Setting::class) ?? false;
    }
}

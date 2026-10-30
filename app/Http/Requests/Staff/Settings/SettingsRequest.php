<?php

namespace App\Http\Requests\Staff\Settings;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

abstract class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.settings') ?? false;
    }

    protected function isOwner(): bool
    {
        return $this->user()?->role === Role::Owner;
    }
}

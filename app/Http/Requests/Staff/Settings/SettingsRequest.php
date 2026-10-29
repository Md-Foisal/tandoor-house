<?php

namespace App\Http\Requests\Staff\Settings;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

abstract class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('staff.settings');
    }

    protected function isOwner(): bool
    {
        return $this->user()?->role === Role::Owner;
    }
}

<?php

namespace App\Http\Requests\Staff\Team;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ResendInviteRequest extends FormRequest
{
    /** Same rules as creating and editing people: the policy and TeamRules. */
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ! $this->user()?->is($target)
            && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}

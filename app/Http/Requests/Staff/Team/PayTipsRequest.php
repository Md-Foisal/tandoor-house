<?php

namespace App\Http\Requests\Staff\Team;

use App\Actions\Team\WeekRange;
use App\Models\TipAllocation;
use Illuminate\Foundation\Http\FormRequest;

class PayTipsRequest extends FormRequest
{
    /** Handing over money is the owner's call. */
    public function authorize(): bool
    {
        return $this->user()?->can('pay', TipAllocation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['week' => ['required', 'date_format:Y-m-d']];
    }

    public function week(): WeekRange
    {
        return WeekRange::from($this->string('week')->toString());
    }
}

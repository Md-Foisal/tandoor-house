<?php

namespace App\Http\Requests\Staff\Team;

use App\Actions\Team\WeekRange;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RecordTipsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.team') ?? false;
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

    /**
     * Tips are only shared out once the week is over, so late tips and forgotten clock-outs are in.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('week') && $this->week()->end()->isFuture()) {
                $validator->errors()->add('week', __('staff_team.errors.tips_week_open'));
            }
        }];
    }
}

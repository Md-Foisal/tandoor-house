<?php

namespace App\Http\Requests\Staff\Reports;

use App\Enums\OrderChannel;
use App\Reports\ReportRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('staff.reports');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $custom = $this->input('period') === 'custom';

        return [
            'period' => ['nullable', Rule::in(ReportRange::PERIODS)],
            'from' => [Rule::requiredIf($custom), 'nullable', 'date_format:Y-m-d'],
            'to' => [Rule::requiredIf($custom), 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'channel' => ['nullable', Rule::enum(OrderChannel::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('period') !== 'custom') {
                return;
            }

            $tz = (string) config('app.timezone');
            $days = ReportRange::countDays(
                CarbonImmutable::parse((string) $this->input('from'), $tz),
                CarbonImmutable::parse((string) $this->input('to'), $tz)->addDay(),
            );

            if ($days > ReportRange::MAX_DAYS) {
                $validator->errors()->add('to', __('staff_reports.errors.too_long', ['days' => ReportRange::MAX_DAYS]));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => __('staff_reports.errors.dates_required'),
            'to.required' => __('staff_reports.errors.dates_required'),
            '*.date_format' => __('staff_reports.errors.bad_date'),
            'to.after_or_equal' => __('staff_reports.errors.end_before_start'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('staff.reports.index');
    }

    public function range(): ReportRange
    {
        $period = (string) ($this->validated('period') ?? 'this_week');

        return ReportRange::resolve(
            $period,
            $this->validated('from'),
            $this->validated('to'),
            OrderChannel::tryFrom((string) $this->validated('channel')),
        );
    }
}

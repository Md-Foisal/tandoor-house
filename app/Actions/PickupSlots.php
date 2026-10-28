<?php

namespace App\Actions;

use App\Services\OpeningHours;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Collection times: today if there is still time, otherwise the next
 * opening day. Slots start after the kitchen's lead time and sit inside
 * service periods.
 */
class PickupSlots
{
    public function __construct(
        private readonly OpeningHours $hours,
        private readonly Settings $settings,
    ) {}

    /**
     * @return array{asap: bool, lead_minutes: int, day: string|null, slots: list<array{value: string, label: string}>}
     */
    public function handle(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now())->setTimezone(config('app.timezone'));
        $lead = (int) $this->settings->get('pickup_lead_minutes');
        $step = max(5, (int) $this->settings->get('pickup_slot_minutes'));
        $earliest = $now->addMinutes($lead);

        for ($offset = 0; $offset < 8; $offset++) {
            $date = $now->addDays($offset);
            $slots = [];

            foreach ($this->hours->periodsOn($date) as [$opens, $closes]) {
                for ($at = $opens; $at->lessThanOrEqualTo($closes->subMinutes($step)); $at = $at->addMinutes($step)) {
                    if ($at->greaterThanOrEqualTo($earliest)) {
                        $slots[] = ['value' => $at->toIso8601String(), 'label' => $at->format('H:i')];
                    }
                }
            }

            if ($slots !== []) {
                return [
                    'asap' => $this->asapAvailable($now, $lead),
                    'lead_minutes' => $lead,
                    'day' => $offset === 0 ? null : $date->translatedFormat('l j F'),
                    'slots' => $slots,
                ];
            }
        }

        return ['asap' => false, 'lead_minutes' => $lead, 'day' => null, 'slots' => []];
    }

    public function asapAvailable(?CarbonInterface $now = null, ?int $lead = null): bool
    {
        $now = CarbonImmutable::instance($now ?? now())->setTimezone(config('app.timezone'));
        $period = $this->hours->currentPeriod($now);
        $lead ??= (int) $this->settings->get('pickup_lead_minutes');

        return $period !== null && $now->addMinutes($lead)->lessThanOrEqualTo($period[1]);
    }

    public function isValid(CarbonInterface $slot, ?CarbonInterface $now = null): bool
    {
        foreach ($this->handle($now)['slots'] as $candidate) {
            if (CarbonImmutable::parse($candidate['value'])->equalTo($slot)) {
                return true;
            }
        }

        return false;
    }
}

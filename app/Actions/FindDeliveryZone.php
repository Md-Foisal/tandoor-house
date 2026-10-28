<?php

namespace App\Actions;

use App\Models\DeliveryZone;

class FindDeliveryZone
{
    /**
     * The active zone covering a postcode. Prefixes are compared with the
     * outward code, so "E1" covers E1 and E1W but never E14.
     */
    public function handle(string $postcode): ?DeliveryZone
    {
        $normalised = UkFormats::postcode($postcode);

        if ($normalised === null) {
            return null;
        }

        $outward = UkFormats::outward($normalised);

        return DeliveryZone::query()
            ->where('is_active', true)
            ->orderBy('fee')
            ->get()
            ->first(fn (DeliveryZone $zone) => collect($zone->postcode_prefixes)
                ->contains(fn (string $prefix) => $this->covers(strtoupper($prefix), $outward)));
    }

    private function covers(string $prefix, string $outward): bool
    {
        if ($outward === $prefix) {
            return true;
        }

        return str_starts_with($outward, $prefix) && ctype_alpha($outward[strlen($prefix)]);
    }
}

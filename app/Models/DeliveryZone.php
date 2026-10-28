<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property list<string> $postcode_prefixes
 * @property int $fee
 * @property int $minimum_order
 * @property int $eta_minutes
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'postcode_prefixes', 'fee', 'minimum_order', 'eta_minutes', 'is_active'])]
class DeliveryZone extends Model
{
    /** @use HasFactory<DeliveryZoneFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'postcode_prefixes' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

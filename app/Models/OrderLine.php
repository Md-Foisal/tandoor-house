<?php

namespace App\Models;

use App\Enums\LineStatus;
use App\Enums\Station;
use Carbon\CarbonImmutable;
use Database\Factories\OrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $menu_item_id
 * @property int|null $item_variant_id
 * @property string $name
 * @property string|null $variant_name
 * @property list<array{name: string, price: int}> $modifiers
 * @property int $unit_price
 * @property int $quantity
 * @property int $vat_rate
 * @property Station $station
 * @property LineStatus $status
 * @property string|null $notes
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $bumped_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['menu_item_id', 'item_variant_id', 'name', 'variant_name', 'modifiers', 'unit_price', 'quantity', 'vat_rate', 'station', 'status', 'notes', 'started_at', 'bumped_at'])]
class OrderLine extends Model
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'modifiers' => 'array',
            'station' => Station::class,
            'status' => LineStatus::class,
            'started_at' => 'datetime',
            'bumped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}

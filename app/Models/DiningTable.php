<?php

namespace App\Models;

use App\Enums\TableStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $label
 * @property int $seats
 * @property string $area
 * @property int $x
 * @property int $y
 * @property int $width
 * @property int $height
 * @property string $shape
 * @property TableStatus $status
 * @property string $qr_token
 * @property int|null $party_size
 * @property CarbonImmutable|null $seated_at
 * @property int|null $merged_into_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label', 'seats', 'area', 'x', 'y', 'width', 'height', 'shape', 'status', 'qr_token', 'party_size', 'seated_at', 'merged_into_id'])]
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'seated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<TableRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(TableRequest::class);
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'merged_into_id');
    }
}

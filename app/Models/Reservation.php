<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property int $party_size
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int|null $dining_table_id
 * @property ReservationStatus $status
 * @property int $deposit
 * @property PaymentStatus|null $deposit_status
 * @property string|null $occasion
 * @property string|null $notes
 * @property CarbonImmutable|null $reminded_at
 * @property CarbonImmutable|null $seated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['reference', 'user_id', 'name', 'email', 'phone', 'party_size', 'starts_at', 'ends_at', 'dining_table_id', 'status', 'deposit', 'deposit_status', 'occasion', 'notes', 'reminded_at', 'seated_at'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'deposit_status' => PaymentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminded_at' => 'datetime',
            'seated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }
}

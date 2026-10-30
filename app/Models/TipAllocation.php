<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TipAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's share of a week's tips. period is the Monday the week starts.
 *
 * @property int $id
 * @property CarbonImmutable $period
 * @property int $user_id
 * @property int $amount pence
 * @property int $minutes_worked
 * @property int|null $recorded_by
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['period', 'user_id', 'amount', 'minutes_worked', 'recorded_by', 'paid_at'])]
class TipAllocation extends Model
{
    /** @use HasFactory<TipAllocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period' => 'date:Y-m-d',
            'amount' => 'integer',
            'minutes_worked' => 'integer',
            'paid_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

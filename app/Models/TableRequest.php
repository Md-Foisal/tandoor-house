<?php

namespace App\Models;

use App\Enums\TableRequestType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dining_table_id
 * @property TableRequestType $type
 * @property CarbonImmutable|null $handled_at
 * @property int|null $handled_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['type', 'handled_at', 'handled_by'])]
class TableRequest extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TableRequestType::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }
}

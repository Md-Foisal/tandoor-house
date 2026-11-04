<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $event
 * @property string $subject
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $user_id
 * @property string $description
 * @property array<string, mixed>|null $properties
 * @property CarbonImmutable $created_at
 */
#[Fillable(['event', 'subject_type', 'subject_id', 'user_id', 'description', 'properties'])]
class ActivityLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * Twelve months is enough to answer "who changed this"; money records live in their own tables.
     *
     * @return Builder<ActivityLog>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(12));
    }

    protected function casts(): array
    {
        return [
            'properties' => 'array',
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
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

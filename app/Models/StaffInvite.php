<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff member's set-password invite. Kept apart from the password broker
 * so a forgot-password token never doubles as an invite and clearing reset
 * tokens leaves invites alone. Only the SHA-256 of the token is stored.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property int|null $invited_by
 * @property CarbonImmutable $sent_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property-read User $user
 */
class StaffInvite extends Model
{
    use MassPrunable;

    protected $guarded = ['id'];

    /**
     * Accepted or expired more than 30 days ago: the token is dead and only adds clutter.
     *
     * @return Builder<StaffInvite>
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subDays(30);

        return static::where(fn ($q) => $q->where('accepted_at', '<', $cutoff)->orWhere('expires_at', '<', $cutoff));
    }

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<StaffInvite>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}

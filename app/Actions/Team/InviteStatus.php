<?php

namespace App\Actions\Team;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where a staff invite stands. An invite leaves an activity log row and a
 * row in password_reset_tokens; setting the password removes the token, so
 * "logged but no token" means accepted.
 */
class InviteStatus
{
    public const EVENT = 'automation.team.invite_sent';

    public const VALID_DAYS = 7;

    public static function expiresAfter(Carbon $sent): Carbon
    {
        return $sent->copy()->addDays(self::VALID_DAYS);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, array{state: string, sent_at: string|null}>
     */
    public function forUsers(Collection $users): array
    {
        $invited = ActivityLog::query()
            ->where('event', self::EVENT)
            ->where('subject_type', (new User)->getMorphClass())
            ->whereIn('subject_id', $users->pluck('id'))
            ->pluck('subject_id')
            ->flip();

        $tokens = DB::table('password_reset_tokens')
            ->whereIn('email', $users->pluck('email'))
            ->pluck('created_at', 'email');

        $out = [];

        foreach ($users as $user) {
            $out[$user->id] = $this->state($invited->has($user->id), $tokens[$user->email] ?? null);
        }

        return $out;
    }

    /**
     * @return array{state: string, sent_at: string|null}
     */
    public function forUser(User $user): array
    {
        return $this->forUsers(collect([$user]))[$user->id];
    }

    /**
     * @return array{state: string, sent_at: string|null}
     */
    private function state(bool $invited, mixed $createdAt): array
    {
        if (! $invited) {
            return ['state' => 'none', 'sent_at' => null];
        }

        if ($createdAt === null) {
            return ['state' => 'accepted', 'sent_at' => null];
        }

        $sent = Carbon::parse($createdAt);

        return [
            'state' => self::expiresAfter($sent)->isPast() ? 'expired' : 'pending',
            'sent_at' => $sent->toIso8601String(),
        ];
    }
}

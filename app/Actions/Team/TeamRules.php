<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Who may create, promote, change or remove whom. Owners can do all of it;
 * managers only look after the floor, kitchen and riders.
 */
class TeamRules
{
    /** @var list<Role> */
    private const FRONT_LINE = [Role::Waiter, Role::Cashier, Role::Kitchen, Role::Rider];

    /**
     * Roles the actor may hand out.
     *
     * @return list<Role>
     */
    public static function assignable(User $actor): array
    {
        return $actor->role === Role::Owner
            ? [Role::Waiter, Role::Cashier, Role::Kitchen, Role::Rider, Role::Manager, Role::Owner]
            : self::FRONT_LINE;
    }

    /** Whether the actor may edit this person at all (phone or role). */
    public static function canEdit(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return true;
        }

        if ($actor->role === Role::Owner) {
            return true;
        }

        return in_array($target->role, self::FRONT_LINE, true);
    }

    /** Owners roster anyone; managers roster front-line staff and themselves. */
    public static function canRoster(User $actor, User $person): bool
    {
        return $actor->role === Role::Owner || $actor->is($person) || in_array($person->role, self::FRONT_LINE, true);
    }

    /** Removing someone from the team is for owners, never for yourself. */
    public static function canRemove(User $actor, User $target): bool
    {
        return $actor->role === Role::Owner && ! $actor->is($target) && $target->role->isStaff();
    }

    public static function ensureCanAssign(User $actor, Role $role): void
    {
        if (! in_array($role, self::assignable($actor), true)) {
            abort(403, __('staff_team.errors.role_not_allowed'));
        }
    }

    /** Call inside the transaction that changes the role, so the owner rows stay locked until it commits. */
    public static function ensureOwnerRemains(User $target, Role $newRole): void
    {
        if ($target->role !== Role::Owner || $newRole === Role::Owner) {
            return;
        }

        $others = User::query()->where('role', Role::Owner)->whereKeyNot($target->id)->lockForUpdate()->pluck('id')->count();

        if ($others === 0) {
            throw ValidationException::withMessages(['role' => __('staff_team.errors.last_owner')]);
        }
    }
}

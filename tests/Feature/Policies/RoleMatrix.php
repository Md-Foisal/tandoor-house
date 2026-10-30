<?php

namespace Tests\Feature\Policies;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Drives one request as one role and checks the answer against the roles
 * plan §2 allows. Allowed means anything but a 403 (a validation bounce is
 * fine: the request got past the policy); denied means a 403; guests are
 * sent to sign in.
 */
final class RoleMatrix
{
    /** @var list<string> */
    public const ROLES = ['guest', 'customer', 'waiter', 'cashier', 'kitchen', 'rider', 'manager', 'owner'];

    public const STAFF = ['waiter', 'cashier', 'kitchen', 'rider', 'manager', 'owner'];

    public const FLOOR = ['waiter', 'cashier', 'manager', 'owner'];

    public const KITCHEN = ['kitchen', 'manager', 'owner'];

    public const DELIVERIES = ['rider', 'manager', 'owner'];

    public const MANAGEMENT = ['manager', 'owner'];

    public const OWNER = ['owner'];

    public static function actor(string $role): ?User
    {
        return $role === 'guest' ? null : User::factory()->role(Role::from($role))->create();
    }

    /**
     * @param  Closure(?User): (string|array{0: string, 1: array<string, mixed>})  $target  the URI, or the URI and payload
     * @param  list<string>  $allowed
     */
    public static function check(TestCase $test, string $role, string $method, Closure $target, array $allowed): TestResponse
    {
        $actor = self::actor($role);
        $resolved = $target($actor);
        [$uri, $payload] = is_array($resolved) ? $resolved : [$resolved, []];

        if ($actor !== null) {
            $test->actingAs($actor);
        }

        $response = $test->from('/staff')->{$method}($uri, $payload);

        if ($actor === null) {
            return $response->assertRedirect('/login');
        }

        if (in_array($role, $allowed, true)) {
            expect($response->getStatusCode())->not->toBe(403, "{$role} should be allowed to {$method} {$uri}");
        } else {
            $response->assertForbidden();
        }

        return $response;
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Team\InviteStatus;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\StaffInvite;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class StaffInviteController extends Controller
{
    use PasswordValidationRules;

    public function __construct(private readonly ActivityRecorder $activity) {}

    public function show(Request $request, string $token): Response
    {
        $email = (string) $request->query('email', '');

        return Inertia::render('auth/accept-invite', [
            'token' => $token,
            'email' => $email,
            'valid' => $this->invite($email, $token) !== null,
            'validDays' => InviteStatus::VALID_DAYS,
            'restaurant' => config('restaurant.name'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = DB::transaction(function () use ($data, $token) {
            $invite = $this->invite((string) $data['email'], $token, lock: true);

            if ($invite === null) {
                return null;
            }

            $invite->user->forceFill(['password' => $data['password']])->save();
            $invite->forceFill(['accepted_at' => now()])->save();
            DB::table('password_reset_tokens')->where('email', $invite->user->email)->delete();

            return $invite->user;
        });

        if ($user === null) {
            return back()->withErrors(['token' => __('staff_team.invite.page.invalid')]);
        }

        $this->activity->record('team.invite_accepted', __('staff_team.log.invite_accepted', ['name' => $user->name]), $user, by: $user);

        return to_route('login')->with('status', __('staff_team.invite.page.done'));
    }

    /**
     * Finds the unexpired, unused invite for this token and email. The lookup
     * is by SHA-256 of the token, so no comparison leaks timing on the secret.
     */
    private function invite(string $email, string $token, bool $lock = false): ?StaffInvite
    {
        if ($email === '' || $token === '') {
            return null;
        }

        $invite = StaffInvite::query()
            ->usable()
            ->where('token_hash', StaffInvite::hashToken($token))
            ->with('user')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        $user = $invite?->user;

        if ($user === null || ! hash_equals(mb_strtolower($user->email), mb_strtolower($email)) || ! $user->role->isStaff()) {
            return null;
        }

        return $invite;
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Team\InviteStatus;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            'valid' => $this->invitee($email, $token) !== null,
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

        $user = $this->invitee($data['email'], $token);

        if ($user === null) {
            return back()->withErrors(['email' => __('staff_team.invite.page.expired_title')]);
        }

        DB::transaction(function () use ($user, $data) {
            $user->forceFill(['password' => $data['password']])->save();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });

        $this->activity->record('team.invite_accepted', __('staff_team.log.invite_accepted', ['name' => $user->name]), $user, by: $user);

        return to_route('login')->with('status', __('staff_team.invite.page.done'));
    }

    private function invitee(string $email, string $token): ?User
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($row === null || ! Hash::check($token, (string) $row->token)) {
            return null;
        }

        if ($row->created_at === null || InviteStatus::expiresAfter(Carbon::parse($row->created_at))->isPast()) {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        return $user?->role->isStaff() ? $user : null;
    }
}

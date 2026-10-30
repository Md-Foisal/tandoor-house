<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Account\EraseAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\ActivityRecorder;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'marketingOptIn' => $request->user()->fresh()->marketing_opt_in,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, ActivityRecorder $activity): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $consentChanged = $request->user()->isDirty('marketing_opt_in');

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($consentChanged) {
            $activity->record(
                $request->user()->marketing_opt_in ? 'marketing.opted_in' : 'marketing.opted_out',
                $request->user()->marketing_opt_in ? 'A customer opted in to marketing.' : 'A customer opted out of marketing.',
                $request->user(),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, EraseAccount $erase): RedirectResponse
    {
        $user = $request->user();

        $erase->ensureCanErase($user);

        Auth::logout();

        $erase->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

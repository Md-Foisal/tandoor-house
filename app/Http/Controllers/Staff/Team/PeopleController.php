<?php

namespace App\Http\Controllers\Staff\Team;

use App\Actions\Team\CreateStaffMember;
use App\Actions\Team\RemoveFromTeam;
use App\Actions\Team\SendStaffInvite;
use App\Actions\Team\UpdateStaffMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Team\ResendInviteRequest;
use App\Http\Requests\Staff\Team\StoreStaffRequest;
use App\Http\Requests\Staff\Team\UpdateStaffRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PeopleController extends Controller
{
    public function store(StoreStaffRequest $request, CreateStaffMember $create): RedirectResponse
    {
        $user = $create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.invited', ['name' => $user->name, 'email' => $user->email])]);

        return back();
    }

    public function invite(ResendInviteRequest $request, User $user, SendStaffInvite $invite): RedirectResponse
    {
        $invite->resend($request->user(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.invite_resent', ['name' => $user->name])]);

        return back();
    }

    public function update(UpdateStaffRequest $request, User $user, UpdateStaffMember $update): RedirectResponse
    {
        $update($request->user(), $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.updated', ['name' => $user->name])]);

        return back();
    }

    public function destroy(Request $request, User $user, RemoveFromTeam $remove): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $remove($request->user(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.removed', ['name' => $user->name])]);

        return back();
    }
}

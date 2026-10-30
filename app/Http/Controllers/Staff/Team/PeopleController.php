<?php

namespace App\Http\Controllers\Staff\Team;

use App\Actions\Team\CreateStaffMember;
use App\Actions\Team\RemoveFromTeam;
use App\Actions\Team\UpdateStaffMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Team\StoreStaffRequest;
use App\Http\Requests\Staff\Team\UpdateStaffRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PeopleController extends Controller
{
    public function store(StoreStaffRequest $request, CreateStaffMember $create): RedirectResponse
    {
        $user = $create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.added', ['name' => $user->name])]);

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
        $remove($request->user(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.removed', ['name' => $user->name])]);

        return back();
    }
}

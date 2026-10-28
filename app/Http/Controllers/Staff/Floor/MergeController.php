<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Actions\Floor\MergeTables;
use App\Actions\Floor\UnmergeTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Floor\MergeTablesRequest;
use App\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MergeController extends Controller
{
    public function store(MergeTablesRequest $request, MergeTables $merge): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->input('tables'));
        $lead = $merge->handle($ids, $request->integer('lead_id') ?: null, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_floor.toast.merged', ['lead' => $lead->label])]);

        return back();
    }

    public function destroy(Request $request, DiningTable $table, UnmergeTable $unmerge): RedirectResponse
    {
        $lead = $unmerge->handle($table, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_floor.toast.unmerged', ['lead' => $lead->label])]);

        return back();
    }
}

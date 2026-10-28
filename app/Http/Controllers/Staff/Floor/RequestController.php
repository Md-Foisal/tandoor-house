<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Actions\Floor\HandleTableRequest;
use App\Http\Controllers\Controller;
use App\Models\TableRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RequestController extends Controller
{
    public function __invoke(Request $request, TableRequest $tableRequest, HandleTableRequest $handle): RedirectResponse
    {
        $handle->handle($tableRequest, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_floor.toast.handled', ['table' => $tableRequest->diningTable->label])]);

        return back();
    }
}

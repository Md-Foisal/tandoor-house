<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Floor\BuildFloorBoard;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FloorController extends Controller
{
    public function __invoke(BuildFloorBoard $board): Response
    {
        // Both props come from one pass over the data, whichever is asked for first.
        $built = null;
        $load = function () use (&$built, $board) {
            return $built ??= $board->handle();
        };

        return Inertia::render('staff/floor/index', [
            'tables' => Inertia::defer(fn () => $load()['tables'], 'floor'),
            'upcoming' => Inertia::defer(fn () => $load()['upcoming'], 'floor'),
        ]);
    }
}

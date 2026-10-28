<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('staff/today');
    }
}

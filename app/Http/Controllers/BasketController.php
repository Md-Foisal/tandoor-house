<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class BasketController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('basket');
    }
}

<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('account/show');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $role = $request->user()->role;

        $view = match ($role) {
            UserRole::Finance => 'dashboard.finance',
            UserRole::Teacher => 'dashboard.teacher',
            UserRole::Admin, UserRole::SuperAdmin => 'dashboard.admin',
        };

        return view($view, [
            'user' => $request->user(),
        ]);
    }
}

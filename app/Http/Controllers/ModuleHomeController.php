<?php

namespace App\Http\Controllers;

use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleHomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->session()->forget(\App\Support\Navigation::SESSION_KEY);

        $user = $request->user();

        return view('modules.home', [
            'user' => $user,
            'modules' => Modules::for($user->loadMissing('staff')),
            'schoolName' => \App\Models\SchoolSetting::schoolName(),
            'academicYear' => \App\Support\AcademicYear::current(),
        ]);
    }
}

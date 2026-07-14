<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ModulePlaceholderController extends Controller
{
    public function __invoke(string $title, ?string $note = null): View
    {
        return view('modules.placeholder', [
            'title' => $title,
            'note' => $note ?? 'This module shell is ready. Full screens arrive in later weekly milestones.',
        ]);
    }
}

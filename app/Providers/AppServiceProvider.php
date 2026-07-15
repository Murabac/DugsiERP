<?php

namespace App\Providers;

use App\Models\SchoolSetting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer([
            'layouts.app',
            'layouts.guest',
            'grades.print',
            'grades.report',
            'attendance.print',
            'timetable.print',
        ], function ($view) {
            try {
                $view->with([
                    'schoolName' => SchoolSetting::schoolName(),
                    'schoolLocation' => SchoolSetting::schoolLocation(),
                    'schoolTagline' => SchoolSetting::schoolTagline(),
                    'schoolLetterheadSub' => SchoolSetting::schoolLetterheadSub(),
                ]);
            } catch (\Throwable) {
                $view->with([
                    'schoolName' => 'Qudus Secondary School',
                    'schoolLocation' => 'Somaliland',
                    'schoolTagline' => 'Secondary School',
                    'schoolLetterheadSub' => 'Secondary School · Somaliland',
                ]);
            }
        });
    }
}

<?php

use App\Enums\AcademicTerm;
use App\Models\Grade;
use App\Models\SchoolSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'term_marks_term_1' => '20',
            'term_marks_term_2' => '20',
            'term_marks_term_3' => '20',
            'term_marks_final_exam' => '40',
        ];

        foreach ($defaults as $key => $value) {
            if (! SchoolSetting::query()->where('key', $key)->exists()) {
                SchoolSetting::query()->create(['key' => $key, 'value' => $value]);
            }
        }

        Schema::table('grades', function (Blueprint $table) {
            $table->decimal('score_marks', 5, 2)->nullable()->after('academic_year');
        });

        $maxByTerm = [
            AcademicTerm::Term1->value => 20.0,
            AcademicTerm::Term2->value => 20.0,
            AcademicTerm::Term3->value => 20.0,
            AcademicTerm::FinalExam->value => 40.0,
        ];

        Grade::query()->whereNull('score_marks')->orderBy('id')->chunkById(200, function ($grades) use ($maxByTerm) {
            foreach ($grades as $grade) {
                $termValue = $grade->term instanceof AcademicTerm
                    ? $grade->term->value
                    : (string) $grade->getRawOriginal('term');
                $max = $maxByTerm[$termValue] ?? 100.0;
                $percent = (float) $grade->score_percent;
                $marks = round(($percent / 100) * $max, 2);
                $grade->forceFill(['score_marks' => $marks])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('score_marks');
        });

        SchoolSetting::query()->whereIn('key', [
            'term_marks_term_1',
            'term_marks_term_2',
            'term_marks_term_3',
            'term_marks_final_exam',
        ])->delete();
    }
};

<?php

namespace Tests\Feature;

use App\Enums\ClassStatus;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\SchoolWeek;
use App\Support\Subjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): void
    {
        foreach (Subjects::all() as $i => $name) {
            Subject::query()->create(['name' => $name, 'sort_order' => $i + 1]);
        }
    }

    private function assignTeacher(string $code, string $name, string $subjectName): Staff
    {
        $subject = Subject::query()->where('name', $subjectName)->firstOrFail();
        $teacher = Staff::query()->create([
            'employee_code' => $code,
            'full_name' => $name,
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => $subjectName,
            'status' => StaffStatus::Active,
        ]);
        TeacherSubjectAssignment::query()->create([
            'staff_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        return $teacher;
    }

    public function test_admin_can_save_timetable_slot_uses_class_room(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'A',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'room' => 'R-2A',
            'status' => ClassStatus::Active,
        ]);
        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $teacher = $this->assignTeacher('EMP-301', 'Math Teacher', 'Mathematics');

        $this->actingAs($admin)
            ->post(route('timetable.upsert'), [
                'class_id' => $class->id,
                'day_of_week' => 'sat',
                'period_number' => 1,
                'subject_id' => $math->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('timetable_slots', [
            'class_id' => $class->id,
            'day_of_week' => 'sat',
            'period_number' => 1,
            'teacher_id' => $teacher->id,
            'room' => 'R-2A',
        ]);
    }

    public function test_teacher_conflict_is_rejected(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();
        $classA = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $classB = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1B',
            'status' => ClassStatus::Active,
        ]);
        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $teacher = Staff::query()->create([
            'employee_code' => 'EMP-302',
            'full_name' => 'Busy Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);

        TimetableSlot::query()->create([
            'class_id' => $classA->id,
            'academic_year' => $year,
            'day_of_week' => 'mon',
            'period_number' => 2,
            'start_time' => '08:45',
            'end_time' => '09:30',
            'subject_id' => $math->id,
            'teacher_id' => $teacher->id,
            'room' => 'R-1A',
        ]);

        $this->actingAs($admin)
            ->post(route('timetable.upsert'), [
                'class_id' => $classB->id,
                'day_of_week' => 'mon',
                'period_number' => 2,
                'subject_id' => $math->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertSessionHasErrors('teacher_id');
    }

    public function test_generate_uses_class_room_and_avoids_teacher_conflicts(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();

        foreach (Subjects::all() as $i => $name) {
            $this->assignTeacher('EMP-G'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), $name.' Teacher', $name);
        }

        $classA = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $classB = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1B',
            'status' => ClassStatus::Active,
        ]);

        $periods = SchoolWeek::defaultWeeklyPeriods();

        $this->actingAs($admin)
            ->post(route('timetable.generate'), [
                'class_id' => $classA->id,
                'periods' => $periods,
            ])
            ->assertRedirect();

        $this->assertTrue(
            TimetableSlot::query()->where('class_id', $classA->id)->where('room', 'R-1A')->exists()
        );
        $this->assertTrue(
            TimetableSlot::query()->where('class_id', $classB->id)->where('room', 'R-1B')->exists()
        );

        $conflicts = TimetableSlot::query()
            ->where('academic_year', $year)
            ->whereNotNull('teacher_id')
            ->select('teacher_id', 'day_of_week', 'period_number')
            ->selectRaw('count(*) as c')
            ->groupBy('teacher_id', 'day_of_week', 'period_number')
            ->having('c', '>', 1)
            ->count();

        $this->assertSame(0, $conflicts);

        $sigA = TimetableSlot::query()
            ->where('class_id', $classA->id)
            ->orderBy('day_of_week')
            ->orderBy('period_number')
            ->get(['day_of_week', 'period_number', 'subject_id'])
            ->map(fn ($s) => $s->day_of_week.'-'.$s->period_number.'-'.$s->subject_id)
            ->implode('|');
        $sigB = TimetableSlot::query()
            ->where('class_id', $classB->id)
            ->orderBy('day_of_week')
            ->orderBy('period_number')
            ->get(['day_of_week', 'period_number', 'subject_id'])
            ->map(fn ($s) => $s->day_of_week.'-'.$s->period_number.'-'.$s->subject_id)
            ->implode('|');

        $this->assertNotSame($sigA, $sigB);
        $this->assertSame(7, SchoolWeek::periodCount());
        $this->assertSame(34, SchoolWeek::weeklyCapacity());
    }

    public function test_generate_keeps_one_teacher_per_subject_per_class(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();
        $english = Subject::query()->where('name', 'English')->firstOrFail();

        // Two English teachers available school-wide.
        $this->assignTeacher('EMP-EN-A', 'English One', 'English');
        $this->assignTeacher('EMP-EN-B', 'English Two', 'English');
        foreach (Subjects::all() as $i => $name) {
            if ($name === 'English') {
                continue;
            }
            $this->assignTeacher('EMP-S'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), $name.' Teacher', $name);
        }

        $classA = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);
        $classB = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);

        $this->actingAs($admin)
            ->post(route('timetable.generate'), ['periods' => SchoolWeek::defaultWeeklyPeriods()])
            ->assertRedirect();

        foreach ([$classA->id, $classB->id] as $classId) {
            $englishTeacherIds = TimetableSlot::query()
                ->where('class_id', $classId)
                ->where('subject_id', $english->id)
                ->pluck('teacher_id')
                ->unique()
                ->values();

            $this->assertGreaterThan(0, $englishTeacherIds->count(), 'English should be placed');
            $this->assertCount(
                1,
                $englishTeacherIds,
                'A class must not split one subject across multiple teachers'
            );
        }

        // Across classes, both English teachers may be used (load split between classes).
        $schoolEnglishTeachers = TimetableSlot::query()
            ->where('academic_year', $year)
            ->where('subject_id', $english->id)
            ->pluck('teacher_id')
            ->unique()
            ->count();
        $this->assertGreaterThanOrEqual(1, $schoolEnglishTeachers);
    }

    public function test_generate_respects_teacher_work_shifts(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();

        $class = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-2A',
            'status' => ClassStatus::Active,
        ]);

        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $teacher = Staff::query()->create([
            'employee_code' => 'EMP-SHIFT',
            'full_name' => 'Morning Math',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'Mathematics',
            'status' => StaffStatus::Active,
            'work_days' => [
                'sat' => ['first'],
                'sun' => ['first'],
                'mon' => ['first'],
                'tue' => ['first'],
                'wed' => ['first'],
            ],
        ]);
        TeacherSubjectAssignment::query()->create([
            'staff_id' => $teacher->id,
            'subject_id' => $math->id,
        ]);

        $this->actingAs($admin)
            ->post(route('timetable.generate'), [
                'periods' => ['Mathematics' => 5] + array_fill_keys(
                    array_diff(Subjects::all(), ['Mathematics']),
                    0
                ),
            ])
            ->assertRedirect();

        $slots = TimetableSlot::query()
            ->where('class_id', $class->id)
            ->where('teacher_id', $teacher->id)
            ->get();

        $this->assertGreaterThan(0, $slots->count());
        foreach ($slots as $slot) {
            $this->assertContains((int) $slot->period_number, SchoolWeek::shiftPeriods('first'));
        }
    }

    public function test_generate_respects_teacher_class_assignments(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();

        $classA = SchoolClass::query()->create([
            'form_level' => 3,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-3A',
            'status' => ClassStatus::Active,
        ]);
        $classB = SchoolClass::query()->create([
            'form_level' => 3,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-3B',
            'status' => ClassStatus::Active,
        ]);

        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $teacher = Staff::query()->create([
            'employee_code' => 'EMP-CLASS',
            'full_name' => 'Class A Math Only',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'Mathematics',
            'status' => StaffStatus::Active,
        ]);
        TeacherSubjectAssignment::query()->create([
            'staff_id' => $teacher->id,
            'subject_id' => $math->id,
        ]);
        $teacher->assignedClasses()->sync([$classA->id]);

        $periods = array_fill_keys(Subjects::all(), 0);
        $periods['Mathematics'] = 3;

        $this->actingAs($admin)
            ->post(route('timetable.generate'), ['periods' => $periods])
            ->assertRedirect();

        $this->assertTrue(
            TimetableSlot::query()
                ->where('class_id', $classA->id)
                ->where('teacher_id', $teacher->id)
                ->exists()
        );
        $this->assertFalse(
            TimetableSlot::query()
                ->where('class_id', $classB->id)
                ->where('teacher_id', $teacher->id)
                ->exists()
        );
    }

    public function test_teacher_sees_personal_and_class_timetables(): void
    {
        $this->seedCatalog();
        $year = AcademicYear::current();
        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $english = Subject::query()->where('name', 'English')->firstOrFail();

        $myStaff = Staff::query()->create([
            'employee_code' => 'EMP-303',
            'full_name' => 'My Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $otherStaff = Staff::query()->create([
            'employee_code' => 'EMP-304',
            'full_name' => 'Other Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacherUser = User::factory()->role(UserRole::Teacher)->create([
            'staff_id' => $myStaff->id,
        ]);

        $class = SchoolClass::query()->create([
            'form_level' => 3,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-3A',
            'status' => ClassStatus::Active,
        ]);

        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'tue',
            'period_number' => 3,
            'start_time' => '09:45',
            'end_time' => '10:30',
            'subject_id' => $math->id,
            'teacher_id' => $myStaff->id,
            'room' => 'R-3A',
        ]);
        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'tue',
            'period_number' => 4,
            'start_time' => '10:30',
            'end_time' => '11:15',
            'subject_id' => $english->id,
            'teacher_id' => $otherStaff->id,
            'room' => 'R-3A',
        ]);

        $this->actingAs($teacherUser)
            ->get(route('timetable.index', ['view' => 'mine']))
            ->assertOk()
            ->assertSee('My schedule')
            ->assertSee('Mathematics')
            ->assertSee('Form 3 - A')
            ->assertDontSee('English');

        $this->actingAs($teacherUser)
            ->get(route('timetable.index', ['view' => 'class', 'class' => $class->id]))
            ->assertOk()
            ->assertSee('Class timetable')
            ->assertSee('Mathematics')
            ->assertSee('English')
            ->assertSee('Your period');
    }

    public function test_teacher_cannot_edit_or_generate_slots(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($teacher)
            ->post(route('timetable.upsert'), [
                'class_id' => 1,
                'day_of_week' => 'sat',
                'period_number' => 1,
                'subject_id' => 1,
                'teacher_id' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->post(route('timetable.generate'), [
                'periods' => SchoolWeek::defaultWeeklyPeriods(),
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->post(route('timetable.clear'), [
                'class_id' => 1,
                'day_of_week' => 'sat',
                'period_number' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->post(route('timetable.swap'), [
                'class_id' => 1,
                'from_day' => 'sat',
                'from_period' => 1,
                'to_day' => 'sun',
                'to_period' => 2,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_swap_slots(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();
        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $english = Subject::query()->where('name', 'English')->firstOrFail();
        $mathTeacher = $this->assignTeacher('EMP-401', 'Math T', 'Mathematics');
        $engTeacher = $this->assignTeacher('EMP-402', 'Eng T', 'English');

        $class = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-2A',
            'status' => ClassStatus::Active,
        ]);

        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'sat',
            'period_number' => 1,
            'start_time' => '08:00',
            'end_time' => '08:45',
            'subject_id' => $math->id,
            'teacher_id' => $mathTeacher->id,
            'room' => 'R-2A',
        ]);
        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'sun',
            'period_number' => 2,
            'start_time' => '08:45',
            'end_time' => '09:30',
            'subject_id' => $english->id,
            'teacher_id' => $engTeacher->id,
            'room' => 'R-2A',
        ]);

        $this->actingAs($admin)
            ->post(route('timetable.swap'), [
                'class_id' => $class->id,
                'from_day' => 'sat',
                'from_period' => 1,
                'to_day' => 'sun',
                'to_period' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('timetable_slots', [
            'class_id' => $class->id,
            'day_of_week' => 'sat',
            'period_number' => 1,
            'subject_id' => $english->id,
            'teacher_id' => $engTeacher->id,
        ]);
        $this->assertDatabaseHas('timetable_slots', [
            'class_id' => $class->id,
            'day_of_week' => 'sun',
            'period_number' => 2,
            'subject_id' => $math->id,
            'teacher_id' => $mathTeacher->id,
        ]);
    }

    public function test_swap_rejects_teacher_conflict_in_other_class(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();
        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $english = Subject::query()->where('name', 'English')->firstOrFail();
        $mathTeacher = $this->assignTeacher('EMP-501', 'Math Conflict', 'Mathematics');
        $engTeacher = $this->assignTeacher('EMP-502', 'Eng Conflict', 'English');

        $classA = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $classB = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1B',
            'status' => ClassStatus::Active,
        ]);

        // Math teacher free on Sat P1 in A, but already teaching Mon P2 in B.
        TimetableSlot::query()->create([
            'class_id' => $classA->id,
            'academic_year' => $year,
            'day_of_week' => 'sat',
            'period_number' => 1,
            'start_time' => '08:00',
            'end_time' => '08:45',
            'subject_id' => $math->id,
            'teacher_id' => $mathTeacher->id,
            'room' => 'R-1A',
        ]);
        TimetableSlot::query()->create([
            'class_id' => $classA->id,
            'academic_year' => $year,
            'day_of_week' => 'mon',
            'period_number' => 2,
            'start_time' => '08:45',
            'end_time' => '09:30',
            'subject_id' => $english->id,
            'teacher_id' => $engTeacher->id,
            'room' => 'R-1A',
        ]);
        TimetableSlot::query()->create([
            'class_id' => $classB->id,
            'academic_year' => $year,
            'day_of_week' => 'mon',
            'period_number' => 2,
            'start_time' => '08:45',
            'end_time' => '09:30',
            'subject_id' => $math->id,
            'teacher_id' => $mathTeacher->id,
            'room' => 'R-1B',
        ]);

        $this->actingAs($admin)
            ->post(route('timetable.swap'), [
                'class_id' => $classA->id,
                'from_day' => 'sat',
                'from_period' => 1,
                'to_day' => 'mon',
                'to_period' => 2,
            ])
            ->assertSessionHasErrors([
                'to_day' => 'Cannot move: Math Conflict is already in Form 1 - B (Monday P2) — Mathematics.',
            ]);
    }

    public function test_timetable_shows_suggested_moves_for_empty_cells(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();
        $chemistry = Subject::query()->where('name', 'Chemistry')->firstOrFail();
        $chemTeacher = $this->assignTeacher('EMP-HINT-CHEM', 'Amina Chem', 'Chemistry');

        $classA = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);
        $classB = SchoolClass::query()->create([
            'form_level' => 4,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);

        // Chemistry teacher blocked on Sat P1 in Form 4-A; Form 2-B needs Chemistry there.
        TimetableSlot::query()->create([
            'class_id' => $classB->id,
            'academic_year' => $year,
            'day_of_week' => 'sat',
            'period_number' => 1,
            'start_time' => '08:00',
            'end_time' => '08:45',
            'subject_id' => $chemistry->id,
            'teacher_id' => $chemTeacher->id,
            'room' => 'R-4A',
        ]);

        $hints = \App\Support\TimetableMoveHints::forClass($classA, $year);
        $this->assertNotEmpty($hints);
        $this->assertTrue(
            collect($hints)->contains(fn (array $h) => str_contains($h['text'], 'Form 4 - A')
                && str_contains($h['text'], 'Chemistry')
                && str_contains($h['text'], 'Amina Chem')),
            'Expected an unblock hint naming the other class and teacher'
        );
        $this->assertFalse(
            collect($hints)->contains(fn (array $h) => str_contains($h['text'], '↔ this empty')),
            'Must not suggest pointless same-class hole swaps'
        );

        $this->actingAs($admin)
            ->get(route('timetable.index', ['class' => $classA->id]))
            ->assertOk()
            ->assertSee('Suggested moves')
            ->assertSee('Amina Chem')
            ->assertDontSee('↔ this empty');
    }

    public function test_upsert_rejects_teacher_not_assigned_to_subject(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'B',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'room' => 'R-2B',
            'status' => ClassStatus::Active,
        ]);
        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $englishTeacher = $this->assignTeacher('EMP-601', 'English Only', 'English');

        $this->actingAs($admin)
            ->post(route('timetable.upsert'), [
                'class_id' => $class->id,
                'day_of_week' => 'sat',
                'period_number' => 1,
                'subject_id' => $math->id,
                'teacher_id' => $englishTeacher->id,
            ])
            ->assertSessionHasErrors('teacher_id');
    }

    public function test_generate_requires_teachers_for_requested_subjects(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 4,
            'section' => 'A',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'room' => 'R-4A',
            'status' => ClassStatus::Active,
        ]);

        // Only Math has a teacher; defaults request all subjects.
        $this->assignTeacher('EMP-701', 'Only Math', 'Mathematics');

        $this->actingAs($admin)
            ->post(route('timetable.generate'), [
                'periods' => SchoolWeek::defaultWeeklyPeriods(),
            ])
            ->assertSessionHasErrors('periods');
    }

    public function test_admin_print_without_classes_is_not_found(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get(route('timetable.print'))
            ->assertNotFound();
    }

    public function test_admin_can_print_full_school_timetable(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();

        $classA = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1B',
            'status' => ClassStatus::Active,
        ]);

        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $teacher = $this->assignTeacher('EMP-PRINT', 'Print Teacher', 'Mathematics');
        TimetableSlot::query()->create([
            'class_id' => $classA->id,
            'academic_year' => $year,
            'day_of_week' => 'sat',
            'period_number' => 1,
            'start_time' => '07:30',
            'end_time' => '08:10',
            'subject_id' => $math->id,
            'teacher_id' => $teacher->id,
            'room' => 'R-1A',
        ]);

        $this->actingAs($admin)
            ->get(route('timetable.print', ['scope' => 'school']))
            ->assertOk()
            ->assertSee('Full School Timetable')
            ->assertSee($classA->displayName())
            ->assertSee('Form 1 - B')
            ->assertSee('Mathematics');
    }

    public function test_admin_can_view_timetable_requirements(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get(route('timetable.requirements'))
            ->assertOk()
            ->assertSee('Requirements')
            ->assertSee('Need (FT)')
            ->assertSee('Empty / class');
    }

    public function test_requirements_one_ft_table_flags_english_and_math_for_eight_classes(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();

        foreach (range(0, 7) as $i) {
            SchoolClass::query()->create([
                'form_level' => 1,
                'section' => chr(65 + $i),
                'academic_year' => $year,
                'capacity' => 30,
                'status' => ClassStatus::Active,
            ]);
        }

        $report = \App\Support\TimetableRequirements::analyze($year);
        $bySubject = collect($report['subject_ft_check'])->keyBy('subject');

        $this->assertSame(40, $bySubject['English']['lessons_needed']);
        $this->assertFalse($bySubject['English']['ft_enough']);
        $this->assertSame(6, $bySubject['English']['short_by']);
        $this->assertSame('No — short by 6', $bySubject['English']['verdict']);

        $this->assertSame(40, $bySubject['Mathematics']['lessons_needed']);
        $this->assertFalse($bySubject['Mathematics']['ft_enough']);

        $this->assertArrayHasKey('All other subjects', $bySubject);
        $this->assertSame(24, $bySubject['All other subjects']['lessons_needed']);
        $this->assertSame('24 each', $bySubject['All other subjects']['lessons_label']);
        $this->assertTrue($bySubject['All other subjects']['ft_enough']);
        $this->assertSame('Yes (on paper)', $bySubject['All other subjects']['verdict']);
        $this->assertArrayNotHasKey('Physics', $bySubject);

        // 10 subjects: English 2 + Math 2 + 8×1 = 12 FT needed with 0 on roster.
        $this->assertSame(12, $report['ft_teachers_needed_overall']);
        $this->assertSame(0, $report['teachers_on_roster']);
        $this->assertSame(12, $report['teachers_short_overall']);

        $this->actingAs($admin)
            ->get(route('timetable.requirements'))
            ->assertOk()
            ->assertSee('Need (FT)')
            ->assertSee('Short')
            ->assertSee('FT needed by subject')
            ->assertSee('Not enough teachers overall')
            ->assertSee('English')
            ->assertSee('Mathematics')
            ->assertSee('All other subjects')
            ->assertSee('24 each')
            ->assertSee('No −6');
    }

    public function test_teacher_can_view_timetable_requirements(): void
    {
        $this->seedCatalog();
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($teacher)
            ->get(route('timetable.requirements'))
            ->assertOk();
    }

    public function test_finance_cannot_view_timetable_requirements(): void
    {
        $finance = User::factory()->role(UserRole::Finance)->create();

        $this->actingAs($finance)
            ->get(route('timetable.requirements'))
            ->assertForbidden();
    }

    public function test_requirements_counts_empty_periods_against_day_structure(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();
        $capacity = SchoolWeek::weeklyCapacity();
        $math = Subject::query()->where('name', 'Mathematics')->firstOrFail();
        $teacher = $this->assignTeacher('EMP-EMPTY', 'Empty Check', 'Mathematics');

        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);

        // Fill 10 required slots → rest are empty (like “—” on the printed timetable).
        $filled = 0;
        foreach (SchoolWeek::days() as $day) {
            foreach (SchoolWeek::periodsForDay($day) as $period) {
                if ($filled >= 10) {
                    break 2;
                }
                TimetableSlot::query()->create([
                    'class_id' => $class->id,
                    'academic_year' => $year,
                    'day_of_week' => $day,
                    'period_number' => $period['period'],
                    'start_time' => $period['start'],
                    'end_time' => $period['end'],
                    'subject_id' => $math->id,
                    'teacher_id' => $teacher->id,
                    'room' => 'R-1A',
                ]);
                $filled++;
            }
        }

        $report = \App\Support\TimetableRequirements::analyze($year);
        $row = collect($report['class_fill'])->firstWhere('class_id', $class->id);

        $this->assertSame($capacity, $row['required']);
        $this->assertSame(10, $row['filled']);
        $this->assertSame($capacity - 10, $row['empty']);
        $this->assertSame($capacity - 10, $report['empty_periods_per_class']);
        $this->assertStringContainsString('7', $report['day_structure_label']);

        $this->actingAs($admin)
            ->get(route('timetable.requirements'))
            ->assertOk()
            ->assertSee('Empty / class')
            ->assertSee('Empty periods by class')
            ->assertSee('Form 1 - A')
            ->assertDontSee('Peak free');
    }

    public function test_requirements_splits_multi_subject_teacher_capacity(): void
    {
        $this->seedCatalog();
        $year = AcademicYear::current();

        for ($i = 0; $i < 8; $i++) {
            SchoolClass::query()->create([
                'form_level' => 1,
                'section' => chr(65 + $i),
                'academic_year' => $year,
                'capacity' => 30,
                'status' => ClassStatus::Active,
            ]);
        }

        $english = Subject::query()->where('name', 'English')->firstOrFail();
        $somali = Subject::query()->where('name', 'Somali Language')->firstOrFail();
        $teacher = Staff::query()->create([
            'employee_code' => 'EMP-MULTI',
            'full_name' => 'Multi Subject',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'English',
            'status' => StaffStatus::Active,
        ]);
        TeacherSubjectAssignment::query()->create(['staff_id' => $teacher->id, 'subject_id' => $english->id]);
        TeacherSubjectAssignment::query()->create(['staff_id' => $teacher->id, 'subject_id' => $somali->id]);

        $report = \App\Support\TimetableRequirements::analyze($year);
        $englishRow = collect($report['subjects_needing_teachers'])->firstWhere('subject', 'English');

        $this->assertNotNull($englishRow, 'English should need another teacher when one person also teaches Somali');
        $this->assertGreaterThanOrEqual(1, $englishRow['more']);
        $this->assertGreaterThanOrEqual(1, $report['more_teachers_needed']);
    }

    public function test_requirements_flags_when_subject_plan_shorter_than_week(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();

        // Force a short subject plan against a 34-period week (34 → 30).
        $shortPlan = SchoolWeek::defaultWeeklyPeriods();
        $shortPlan['English'] = 1; // was 5 (−4)
        SchoolWeek::setWeeklyPeriods($shortPlan);
        $this->assertSame(30, array_sum(SchoolWeek::weeklyPeriods()));
        $this->assertSame(34, SchoolWeek::weeklyCapacity());

        SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);
        $this->assignTeacher('EMP-PLAN-1', 'Plan Teacher', 'Mathematics');

        $report = \App\Support\TimetableRequirements::analyze($year);
        $this->assertSame(4, $report['plan_gap']);
        // No timetable slots yet → every required period is empty.
        $this->assertSame(34, $report['empty_periods_per_class']);
        $this->assertSame(0, $report['filled_periods_per_class']);
        $this->assertNotNull($report['plan_message']);
        $this->assertStringContainsString('30/34', $report['plan_message']);

        $this->actingAs($admin)
            ->get(route('timetable.requirements'))
            ->assertOk()
            ->assertSee('Empty / class')
            ->assertSee('0/34 filled')
            ->assertSee('30/34');
    }

    public function test_admin_can_save_weekly_periods_used_by_requirements(): void
    {
        $this->seedCatalog();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();

        SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => ClassStatus::Active,
        ]);

        $periods = SchoolWeek::defaultWeeklyPeriods();
        $periods['Mathematics'] = 6;
        $periods['Biology'] = 2; // 5→6 (+1), 3→2 (-1) keeps total 34

        $this->actingAs($admin)
            ->post(route('settings.weekly-periods'), ['periods' => $periods])
            ->assertRedirect(route('settings.index', ['tab' => 'academic']));

        $this->assertSame(6, SchoolWeek::weeklyPeriods()['Mathematics']);
        $this->assertSame(2, SchoolWeek::weeklyPeriods()['Biology']);

        $this->actingAs($admin)
            ->get(route('timetable.index'))
            ->assertOk()
            ->assertSee('name="periods[Mathematics]"', false)
            ->assertSee('value="6"', false);
    }

    public function test_admin_can_reset_weekly_periods_to_factory_defaults(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $custom = SchoolWeek::defaultWeeklyPeriods();
        $custom['Mathematics'] = 9;
        SchoolWeek::setWeeklyPeriods($custom);
        $this->assertSame(9, SchoolWeek::weeklyPeriods()['Mathematics']);

        $this->actingAs($admin)
            ->post(route('settings.weekly-periods'), [
                'reset' => '1',
                'periods' => $custom,
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'academic']));

        $this->assertSame(5, SchoolWeek::weeklyPeriods()['Mathematics']);
        $this->assertSame(5, SchoolWeek::weeklyPeriods()['English']);
        $this->assertSame(34, array_sum(SchoolWeek::weeklyPeriods()));
    }

    public function test_admin_can_set_day_structure_for_34_period_week(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        // Start from a short week, then save the 34-period structure.
        SchoolWeek::setDayStructure([
            'per_day' => ['sat' => 6, 'sun' => 6, 'mon' => 6, 'tue' => 6, 'wed' => 6],
            'definitions' => SchoolWeek::defaultPeriodDefinitions(),
        ]);
        $this->assertSame(30, SchoolWeek::weeklyCapacity());

        $definitions = [];
        foreach (SchoolWeek::defaultPeriodDefinitions() as $def) {
            if ($def['period'] > 7) {
                continue;
            }
            $definitions[$def['period']] = [
                'period' => $def['period'],
                'start' => $def['start'],
                'end' => $def['end'],
            ];
        }

        $this->actingAs($admin)
            ->post(route('settings.day-structure'), [
                'per_day' => [
                    'sat' => 7,
                    'sun' => 7,
                    'mon' => 7,
                    'tue' => 7,
                    'wed' => 6,
                ],
                'definitions' => $definitions,
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'academic']));

        $this->assertSame(34, SchoolWeek::weeklyCapacity());
        $this->assertSame(7, SchoolWeek::periodCount());
        $this->assertTrue(SchoolWeek::dayHasPeriod('sat', 7));
        $this->assertFalse(SchoolWeek::dayHasPeriod('wed', 7));
        $this->assertSame(34, SchoolWeek::fullTimePeriods());
        $this->assertSame(17, SchoolWeek::partTimePeriods());
    }

    public function test_factory_subject_plan_defaults_match_requested_counts(): void
    {
        $plan = SchoolWeek::defaultWeeklyPeriods();
        $this->assertSame(5, $plan['Mathematics']);
        $this->assertSame(5, $plan['English']);
        $this->assertSame(3, $plan['Physics']);
        $this->assertSame(3, $plan['Arabic Language']);
        $this->assertSame(3, $plan['Somali Language']);
        $this->assertSame(3, $plan['Chemistry']);
        $this->assertSame(3, $plan['Islamic Studies']);
        $this->assertSame(3, $plan['Geography']);
        $this->assertSame(3, $plan['History']);
        $this->assertSame(3, $plan['Biology']);
        $this->assertSame(34, array_sum($plan));
        $this->assertSame(34, SchoolWeek::weeklyCapacity());
    }
}

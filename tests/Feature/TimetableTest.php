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

        $this->actingAs($admin)
            ->post(route('timetable.generate'), [
                'class_id' => $classB->id,
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
        $this->assertSame(6, SchoolWeek::periodCount());
        $this->assertSame(30, SchoolWeek::weeklyCapacity());
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
                'class_id' => 1,
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
            ->assertSessionHasErrors('to_day');
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
                'class_id' => $class->id,
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
}

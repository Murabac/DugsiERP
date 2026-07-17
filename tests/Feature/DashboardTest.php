<?php

namespace Tests\Feature;

use App\Enums\ClassStatus;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\SchoolWeek;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_year_scoped_enrolled_counts(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $year = AcademicYear::current();

        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => ClassStatus::Active,
            'room' => 'R-1A',
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-1001',
            'full_name' => 'Ayaan Warsame',
            'dob' => '2010-01-01',
            'gender' => 'female',
            'city' => 'Hargeisa',
            'status' => StudentStatus::Active,
        ]);

        // Active student with no current-year enrollment should not inflate the card.
        Student::query()->create([
            'student_code' => 'STU-1002',
            'full_name' => 'Not Enrolled This Year',
            'dob' => '2010-02-01',
            'gender' => 'male',
            'status' => StudentStatus::Active,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        Staff::query()->create([
            'employee_code' => 'EMP-1001',
            'full_name' => 'Hodan Ali',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
            'date_joined' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Enrolled Students')
            ->assertSee('Active enrollments in '.$year, false)
            ->assertViewHas('stats', fn ($stats) => $stats[0]['value'] === '1' && $stats[0]['label'] === 'Enrolled Students')
            ->assertSee('Total Staff')
            ->assertSee('Active Classes')
            ->assertSee('Form 1 - A')
            ->assertSee('Class fill')
            ->assertSee('Recent Activity')
            ->assertSee('Ayaan Warsame');
    }

    public function test_finance_dashboard_shows_year_scoped_enrolled_counts(): void
    {
        $finance = User::factory()->role(UserRole::Finance)->create();
        $year = AcademicYear::current();

        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'status' => ClassStatus::Active,
            'room' => 'R-1B',
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-2001',
            'full_name' => 'Finance Visible Student',
            'dob' => '2010-01-01',
            'gender' => 'male',
            'status' => StudentStatus::Active,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        $this->actingAs($finance)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Finance Dashboard')
            ->assertSee('Enrolled Students')
            ->assertViewHas('stats', fn ($stats) => $stats[0]['value'] === '1' && $stats[0]['label'] === 'Enrolled Students')
            ->assertSee('Active enrollments in '.$year, false)
            ->assertSee('Fees Collected')
            ->assertSee('$0.00');
    }

    public function test_teacher_dashboard_shows_timetable_sections(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create([
            'name' => 'Abdirahman Farah Jama',
        ]);

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Abdirahman')
            ->assertSee('Periods Today')
            ->assertSee('My Classes')
            ->assertSee("Today's Timetable", false);
    }

    public function test_teacher_dashboard_shows_current_and_done_period_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 08:10:00')); // Wednesday during P1

        $staff = Staff::query()->create([
            'employee_code' => 'EMP-T1',
            'full_name' => 'Linked Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create([
            'name' => 'Linked Teacher',
            'staff_id' => $staff->id,
        ]);

        $year = AcademicYear::current();
        $class = SchoolClass::query()->create([
            'form_level' => 3,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-3A',
            'status' => ClassStatus::Active,
        ]);
        $subject = Subject::query()->create(['name' => 'Mathematics', 'sort_order' => 1]);
        $period = SchoolWeek::period(1);

        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'wed',
            'period_number' => 1,
            'start_time' => $period['start'],
            'end_time' => $period['end'],
            'subject_id' => $subject->id,
            'teacher_id' => $staff->id,
            'room' => 'R-3A',
        ]);

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Now: Mathematics P1', false)
            ->assertSee('Form 3 - A');

        Carbon::setTestNow(Carbon::parse('2026-07-15 15:00:00')); // after last period

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Done for today')
            ->assertDontSee('Now: Mathematics P1')
            ->assertDontSee('Next: Mathematics P1');

        Carbon::setTestNow();
    }
}

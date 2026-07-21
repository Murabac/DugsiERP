<?php

namespace Tests\Feature;

use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_roster_print_view(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);
        $student = Student::query()->create([
            'student_code' => 'STU-P01',
            'full_name' => 'Print Student',
            'dob' => '2010-01-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('classes.roster.print', $class))
            ->assertOk()
            ->assertSee('Class Roster')
            ->assertSee('Print Student');
    }

    public function test_staff_attendance_and_mark_sheet_print_views(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        Staff::query()->create([
            'employee_code' => 'EMP-P1',
            'full_name' => 'Print Staff',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('staff-attendance.print'))
            ->assertOk()
            ->assertSee('Staff Attendance Register')
            ->assertSee('Print Staff');

        $this->actingAs($admin)
            ->get(route('staff-attendance.history.print'))
            ->assertOk()
            ->assertSee('Staff Attendance History');

        $class = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'B',
            'academic_year' => AcademicYear::current(),
            'capacity' => 30,
            'room' => 'R-2B',
            'status' => ClassStatus::Active,
        ]);
        $subject = Subject::query()->create(['name' => 'English', 'sort_order' => 1]);
        $student = Student::query()->create([
            'student_code' => 'STU-P02',
            'full_name' => 'Mark Sheet Student',
            'dob' => '2010-01-01',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('grades.sheet.print', [
                'class' => $class->id,
                'subject' => $subject->id,
                'term' => 'term_1',
            ]))
            ->assertOk()
            ->assertSee('Class Mark Sheet')
            ->assertSee('Mark Sheet Student');
    }

    public function test_report_print_views_and_fee_expense_prints(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->get(route('reports.enrollment.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertSee('Enrollment Report')
            ->assertDontSee('Generate Report');

        $this->actingAs($admin)
            ->get(route('reports.fees.collection.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertDontSee('Export CSV');

        $this->actingAs($admin)
            ->get(route('reports.fees.print'))
            ->assertRedirect(route('reports.fees.collection.print'));

        $this->actingAs($admin)
            ->get(route('reports.fees.students-by-form.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF');

        $this->actingAs($admin)
            ->get(route('reports.fees.income.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF');

        $this->actingAs($admin)
            ->get(route('reports.fees.expenses.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF');

        $this->actingAs($admin)
            ->get(route('reports.fees.net-income.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF');

        $this->actingAs($admin)
            ->get(route('reports.fees.monthly-close.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF');

        $this->actingAs($admin)
            ->get(route('reports.payroll.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF');

        $this->actingAs($admin)
            ->get(route('finance.fee-collection.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF');

        $this->actingAs($admin)
            ->get(route('finance.expenses.print'))
            ->assertOk()
            ->assertSee('Print / Save PDF');
    }
}

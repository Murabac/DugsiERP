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
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\SchoolWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{admin: User, finance: User, teacher: User, class: SchoolClass, student: Student}
     */
    private function seedBasics(): array
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();

        $staff = Staff::query()->create([
            'employee_code' => 'EMP-T01',
            'full_name' => 'Teacher One',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
            'date_joined' => now()->toDateString(),
        ]);

        $teacher = User::factory()->role(UserRole::Teacher)->create([
            'staff_id' => $staff->id,
        ]);

        $year = AcademicYear::current();
        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);

        $subject = Subject::query()->create([
            'name' => 'Math',
            'sort_order' => 1,
        ]);

        $period = SchoolWeek::period(1);
        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'sat',
            'period_number' => 1,
            'start_time' => $period['start'],
            'end_time' => $period['end'],
            'subject_id' => $subject->id,
            'teacher_id' => $staff->id,
            'room' => 'R-1A',
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-RPA1',
            'full_name' => 'Audit Student',
            'dob' => '2010-01-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'need_based_discount' => true,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        return compact('admin', 'finance', 'teacher', 'class', 'student');
    }

    public function test_teacher_cannot_access_admin_or_finance_surfaces(): void
    {
        ['teacher' => $teacher] = $this->seedBasics();

        $forbidden = [
            route('finance.fees-dashboard'),
            route('finance.fee-collection'),
            route('finance.expenses'),
            route('finance.accounting'),
            route('payroll.index'),
            route('documents.index'),
            route('reports.index'),
            route('settings.index'),
            route('staff.index'),
            route('notifications.index'),
            route('students.create'),
            route('classes.manage'),
            route('reports.attendance'),
            route('reports.fees'),
        ];

        foreach ($forbidden as $url) {
            $this->actingAs($teacher)->get($url)->assertForbidden();
        }

        $this->actingAs($teacher)->get(route('transport.index'))->assertForbidden();
    }

    public function test_teacher_can_access_academic_surfaces(): void
    {
        ['teacher' => $teacher, 'class' => $class] = $this->seedBasics();

        $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
        $this->actingAs($teacher)->get(route('classes.index'))->assertOk();
        $this->actingAs($teacher)->get(route('classes.roster', $class))->assertOk();
        $this->actingAs($teacher)->get(route('attendance.index'))->assertOk();
        $this->actingAs($teacher)->get(route('grades.index'))->assertOk();
        $this->actingAs($teacher)->get(route('timetable.index'))->assertOk();
    }

    public function test_teacher_student_profile_hides_fees_and_documents(): void
    {
        ['teacher' => $teacher, 'student' => $student] = $this->seedBasics();

        $this->actingAs($teacher)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertViewHas('canSeeFees', false)
            ->assertViewHas('canSeeDocuments', false)
            ->assertDontSee('Need-based fee discount');

        $this->actingAs($teacher)
            ->get(route('students.show', ['student' => $student, 'tab' => 'fees']))
            ->assertRedirect(route('students.show', ['student' => $student, 'tab' => 'overview']));

        $this->actingAs($teacher)
            ->get(route('students.show', ['student' => $student, 'tab' => 'documents']))
            ->assertRedirect(route('students.show', ['student' => $student, 'tab' => 'overview']));
    }

    public function test_finance_cannot_access_academic_or_admin_surfaces(): void
    {
        ['finance' => $finance, 'class' => $class, 'student' => $student] = $this->seedBasics();

        $forbidden = [
            route('classes.index'),
            route('classes.roster', $class),
            route('classes.manage'),
            route('students.create'),
            route('attendance.index'),
            route('grades.index'),
            route('timetable.index'),
            route('settings.index'),
            route('staff.index'),
            route('notifications.index'),
            route('reports.attendance'),
            route('reports.academic'),
            route('reports.enrollment'),
        ];

        foreach ($forbidden as $url) {
            $this->actingAs($finance)->get($url)->assertForbidden();
        }

        $this->actingAs($finance)->get(route('students.show', $student))->assertOk();
        $this->actingAs($finance)->get(route('transport.index'))->assertOk();
    }

    public function test_finance_can_access_finance_surfaces(): void
    {
        ['finance' => $finance] = $this->seedBasics();

        $this->actingAs($finance)->get(route('dashboard'))->assertOk();
        $this->actingAs($finance)->get(route('finance.fees-dashboard'))->assertOk();
        $this->actingAs($finance)->get(route('finance.fee-collection'))->assertOk();
        $this->actingAs($finance)->get(route('finance.expenses'))->assertOk()->assertSee('Expenses is not available yet');
        $this->actingAs($finance)->get(route('finance.accounting'))->assertOk()->assertSee('Accounting is not available yet');
        $this->actingAs($finance)->get(route('payroll.index'))->assertOk();
        $this->actingAs($finance)->get(route('documents.index'))->assertOk();
        $this->actingAs($finance)->get(route('reports.index'))->assertOk();
        $this->actingAs($finance)->get(route('reports.fees'))->assertOk();
        $this->actingAs($finance)->get(route('reports.payroll'))->assertOk();
    }

    public function test_teacher_cannot_open_grade_boundaries(): void
    {
        ['teacher' => $teacher] = $this->seedBasics();

        $this->actingAs($teacher)->get(route('grades.boundaries'))->assertForbidden();
    }

    public function test_expenses_and_accounting_hidden_from_sidebar(): void
    {
        ['admin' => $admin, 'finance' => $finance] = $this->seedBasics();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Expenses<', false)
            ->assertDontSee('>Accounting<', false);

        $this->actingAs($finance)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Expenses<', false)
            ->assertDontSee('>Accounting<', false);
    }

    public function test_admin_sees_fees_tab_and_need_based_on_student_profile(): void
    {
        ['admin' => $admin, 'student' => $student] = $this->seedBasics();

        $this->actingAs($admin)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertViewHas('canSeeFees', true)
            ->assertViewHas('canSeeDocuments', true)
            ->assertSee('Need-based fee discount');
    }

    public function test_sidebar_matches_role_scope(): void
    {
        ['admin' => $admin, 'finance' => $finance, 'teacher' => $teacher] = $this->seedBasics();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Settings')
            ->assertSee('Staff')
            ->assertSee('Notifications');

        $this->actingAs($finance)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Payroll')
            ->assertSee('Documents')
            ->assertDontSee('>Settings<', false)
            ->assertDontSee('>Staff<', false)
            ->assertDontSee('>Attendance<', false);

        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('Grades')
            ->assertDontSee('>Payroll<', false)
            ->assertDontSee('>Documents<', false)
            ->assertDontSee('>Settings<', false)
            ->assertDontSee('>Finance<', false);
    }
}

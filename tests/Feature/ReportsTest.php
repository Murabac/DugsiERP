<?php

namespace Tests\Feature;

use App\Enums\AcademicTerm;
use App\Enums\AttendanceStatus;
use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\LetterGrade;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\FeeCalculator;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SchoolSetting::set('monthly_fee_usd', '45');
        FeeCalculator::clearSiblingCache();
    }

    /**
     * @return array{admin: User, finance: User, teacher: User, class: SchoolClass, student: Student}
     */
    private function seedBasics(): array
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $year = AcademicYear::current();

        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-R01',
            'full_name' => 'Report Student',
            'dob' => '2010-01-01',
            'gender' => Gender::Female,
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

        return compact('admin', 'finance', 'teacher', 'class', 'student');
    }

    public function test_admin_sees_all_report_cards_finance_sees_financial_only(): void
    {
        ['admin' => $admin, 'finance' => $finance, 'teacher' => $teacher] = $this->seedBasics();

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Attendance Report')
            ->assertSee('Academic Performance')
            ->assertSee('Fee Collection Report')
            ->assertSee('Payroll Report')
            ->assertSee('Enrollment Report');

        $this->actingAs($finance)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Fee Collection Report')
            ->assertSee('Payroll Report')
            ->assertDontSee('Attendance Report')
            ->assertDontSee('Academic Performance')
            ->assertDontSee('Enrollment Report');

        $this->actingAs($teacher)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_finance_cannot_open_admin_only_reports(): void
    {
        ['finance' => $finance] = $this->seedBasics();

        $this->actingAs($finance)->get(route('reports.attendance'))->assertForbidden();
        $this->actingAs($finance)->get(route('reports.academic'))->assertForbidden();
        $this->actingAs($finance)->get(route('reports.enrollment'))->assertForbidden();
        $this->actingAs($finance)->get(route('reports.fees'))->assertOk();
        $this->actingAs($finance)->get(route('reports.payroll'))->assertOk();
    }

    public function test_fee_collection_report_and_csv(): void
    {
        ['finance' => $finance, 'class' => $class, 'student' => $student] = $this->seedBasics();
        $year = AcademicYear::current();
        $month = now()->startOfMonth();

        Invoice::query()->create([
            'invoice_number' => 'INV-R-1',
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'billing_month' => $month->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 20,
            'status' => InvoiceStatus::Partial,
        ]);

        $this->actingAs($finance)
            ->get(route('reports.fees'))
            ->assertOk()
            ->assertSee('Fee Collection Report')
            ->assertSee(Money::format(20))
            ->assertSee(Money::format(45));

        $csv = $this->actingAs($finance)
            ->get(route('reports.fees', ['export' => 'csv']))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $this->assertStringContainsString('Total Due', $csv->streamedContent());
        $this->assertStringContainsString('45.00', $csv->streamedContent());
    }

    public function test_attendance_and_academic_and_enrollment_reports(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedBasics();
        $year = AcademicYear::current();
        $subject = Subject::query()->create(['name' => 'Mathematics', 'sort_order' => 1]);

        AttendanceRecord::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'marked_by' => $admin->id,
        ]);

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => AcademicTerm::Term1,
            'academic_year' => $year,
            'score_percent' => 88,
            'letter_grade' => LetterGrade::A,
            'entered_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.attendance', [
                'apply' => 1,
                'from' => now()->subDay()->toDateString(),
                'to' => now()->toDateString(),
                'class' => $class->id,
            ]))
            ->assertOk()
            ->assertSee('Report Student')
            ->assertSee('100%');

        $this->actingAs($admin)
            ->get(route('reports.academic', [
                'apply' => 1,
                'class' => $class->id,
                'term' => AcademicTerm::Term1->value,
            ]))
            ->assertOk()
            ->assertSee('Report Student')
            ->assertSee('88.0%');

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => AcademicTerm::Term2,
            'academic_year' => $year,
            'score_percent' => 72,
            'letter_grade' => LetterGrade::B,
            'entered_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.academic', [
                'apply' => 1,
                'class' => $class->id,
                'terms' => [AcademicTerm::Term1->value, AcademicTerm::Term2->value],
                'subject' => $subject->id,
                'terms_submitted' => 1,
            ]))
            ->assertOk()
            ->assertSee('Report Student')
            ->assertSee('Term 1')
            ->assertSee('Term 2')
            ->assertSee('Combined')
            ->assertSee('80.0%'); // (88 + 72) / 2

        $this->actingAs($admin)
            ->get(route('reports.academic', [
                'apply' => 1,
                'class' => $class->id,
                'terms_submitted' => 1,
            ]))
            ->assertOk()
            ->assertSee('Select at least one term');

        $this->actingAs($admin)
            ->get(route('reports.enrollment'))
            ->assertOk()
            ->assertSee($class->displayName())
            ->assertSee('Active');
    }

    public function test_payroll_report_page_loads(): void
    {
        ['finance' => $finance] = $this->seedBasics();

        $this->actingAs($finance)
            ->get(route('reports.payroll', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Payroll Report');
    }
}

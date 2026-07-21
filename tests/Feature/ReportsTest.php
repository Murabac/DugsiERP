<?php

namespace Tests\Feature;

use App\Enums\AcademicTerm;
use App\Enums\AttendanceStatus;
use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\LetterGrade;
use App\Enums\PaymentMethod;
use App\Enums\PayrollRunStatus;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Staff;
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
            ->assertSee('Fee Reports')
            ->assertSee('Payroll Report')
            ->assertSee('Enrollment Report');

        $this->actingAs($finance)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Fee Reports')
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

    public function test_fee_reports_hub_lists_collection_and_students_by_form(): void
    {
        ['finance' => $finance] = $this->seedBasics();

        $this->actingAs($finance)
            ->get(route('reports.fees'))
            ->assertOk()
            ->assertSee('Fee Reports')
            ->assertSee('Fee collection')
            ->assertSee('Students by form')
            ->assertSee('Income report')
            ->assertSee('Expense report')
            ->assertSee('Net income')
            ->assertSee('Monthly accounting close');
    }

    public function test_legacy_fees_query_redirects_to_collection_including_csv(): void
    {
        ['finance' => $finance, 'class' => $class, 'student' => $student] = $this->seedBasics();
        $year = AcademicYear::current();
        $month = now()->startOfMonth();

        Invoice::query()->create([
            'invoice_number' => 'INV-R-LEGACY',
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
            ->get(route('reports.fees', ['from' => $month->toDateString(), 'to' => $month->toDateString()]))
            ->assertRedirect(route('reports.fees.collection', [
                'from' => $month->toDateString(),
                'to' => $month->toDateString(),
            ]));

        $this->actingAs($finance)
            ->get(route('reports.fees', ['export' => 'csv']))
            ->assertRedirect(route('reports.fees.collection', ['export' => 'csv']));

        $csv = $this->actingAs($finance)
            ->get(route('reports.fees.collection', ['export' => 'csv']))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $this->assertStringContainsString('45.00', $csv->streamedContent());
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
            ->get(route('reports.fees.collection'))
            ->assertOk()
            ->assertSee('Fee Collection Report')
            ->assertSee(Money::format(20))
            ->assertSee(Money::format(45));

        $this->actingAs($finance)
            ->get(route('reports.fees.collection', ['from' => 'not-a-date', 'to' => 'also-bad']))
            ->assertOk();

        $csv = $this->actingAs($finance)
            ->get(route('reports.fees.collection', ['export' => 'csv']))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $this->assertStringContainsString('Total Due', $csv->streamedContent());
        $this->assertStringContainsString('45.00', $csv->streamedContent());
    }

    public function test_students_by_form_aggregates_sections_and_language_toggle(): void
    {
        ['finance' => $finance, 'teacher' => $teacher] = $this->seedBasics();
        $year = AcademicYear::current();
        $monthYm = now()->format('Y-m');
        $month = now()->startOfMonth()->toDateString();

        $form1b = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'B',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1B',
            'status' => ClassStatus::Active,
        ]);
        $form2 = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-2A',
            'status' => ClassStatus::Active,
        ]);

        $form1a = SchoolClass::query()
            ->where('academic_year', $year)
            ->where('form_level', 1)
            ->where('section', 'A')
            ->firstOrFail();

        $paidA = Student::query()->create([
            'student_code' => 'STU-F1A-P',
            'full_name' => 'Paid Form1A',
            'dob' => '2010-01-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
        $unpaidB = Student::query()->create([
            'student_code' => 'STU-F1B-U',
            'full_name' => 'Unpaid Form1B',
            'dob' => '2010-02-01',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        $partialA = Student::query()->create([
            'student_code' => 'STU-F1A-X',
            'full_name' => 'Partial Form1A',
            'dob' => '2010-03-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
        $paidF2 = Student::query()->create([
            'student_code' => 'STU-F2-P',
            'full_name' => 'Paid Form2',
            'dob' => '2009-01-01',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);

        foreach ([
            [$paidA, $form1a, 10],
            [$unpaidB, $form1b, 11],
            [$partialA, $form1a, 12],
            [$paidF2, $form2, 13],
        ] as [$stu, $cls, $roll]) {
            Enrollment::query()->create([
                'student_id' => $stu->id,
                'class_id' => $cls->id,
                'academic_year' => $year,
                'enrollment_date' => now()->toDateString(),
                'status' => StudentStatus::Active,
                'roll_number' => $roll,
            ]);
        }

        // seedBasics already enrolled Report Student in Form 1-A with no invoice → total only
        Invoice::query()->create([
            'invoice_number' => 'INV-F1A-P',
            'student_id' => $paidA->id,
            'class_id' => $form1a->id,
            'academic_year' => $year,
            'billing_month' => $month,
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 45,
            'status' => InvoiceStatus::Paid,
        ]);
        Invoice::query()->create([
            'invoice_number' => 'INV-F1B-U',
            'student_id' => $unpaidB->id,
            'class_id' => $form1b->id,
            'academic_year' => $year,
            'billing_month' => $month,
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);
        Invoice::query()->create([
            'invoice_number' => 'INV-F1A-X',
            'student_id' => $partialA->id,
            'class_id' => $form1a->id,
            'academic_year' => $year,
            'billing_month' => $month,
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 20,
            'status' => InvoiceStatus::Partial,
        ]);
        Invoice::query()->create([
            'invoice_number' => 'INV-F2-P',
            'student_id' => $paidF2->id,
            'class_id' => $form2->id,
            'academic_year' => $year,
            'billing_month' => $month,
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 45,
            'status' => InvoiceStatus::Paid,
        ]);

        $this->actingAs($teacher)
            ->get(route('reports.fees.students-by-form'))
            ->assertForbidden();

        $defaultLang = $this->actingAs($finance)
            ->get(route('reports.fees.students-by-form', ['month' => $monthYm]))
            ->assertOk()
            ->assertSee('Fasalka')
            ->assertSee('Tirada Guud')
            ->assertSee('Ardayda bixisay')
            ->assertSee('Ardayda qayb bixiyay')
            ->assertSee('Ardayda aan bixin')
            ->assertSee('WADARTA')
            ->assertSee('Fasalka 1')
            ->assertSee('Fasalka 2')
            ->assertViewHas('lang', 'so')
            ->assertViewHas('month', $monthYm)
            ->assertViewHas('rows')
            ->assertViewHas('totals');

        $rows = $defaultLang->viewData('rows');
        $byForm = collect($rows)->keyBy('form_level');
        // Form 1: seedBasics + paid + unpaid + partial = 4 total, 1 paid, 1 partial, 1 unpaid
        $this->assertSame(4, $byForm[1]['total']);
        $this->assertSame(1, $byForm[1]['paid']);
        $this->assertSame(1, $byForm[1]['partial']);
        $this->assertSame(1, $byForm[1]['unpaid']);
        $this->assertSame(45.0, $byForm[1]['paid_amount']);
        $this->assertSame(20.0, $byForm[1]['partial_amount']);
        $this->assertSame(45.0, $byForm[1]['unpaid_amount']);
        $this->assertSame(135.0, $byForm[1]['total_amount']); // paid 45 + partial 45 due + unpaid 45
        // Form 2: 1 paid
        $this->assertSame(1, $byForm[2]['total']);
        $this->assertSame(1, $byForm[2]['paid']);
        $this->assertSame(0, $byForm[2]['partial']);
        $this->assertSame(0, $byForm[2]['unpaid']);
        $this->assertSame(45.0, $byForm[2]['paid_amount']);

        $totals = $defaultLang->viewData('totals');
        $this->assertSame(180.0, $totals['total_amount']);
        $this->assertSame(90.0, $totals['paid_amount']);
        $this->assertSame(20.0, $totals['partial_amount']);
        $this->assertSame(45.0, $totals['unpaid_amount']);
        $defaultLang->assertSee(Money::format(90));
        $defaultLang->assertSee(Money::format(20));
        $defaultLang->assertSee(Money::format(45));

        $summary = collect($defaultLang->viewData('summary'))->keyBy('key');
        $this->assertSame(5, $summary['students']['students']); // 4 form1 + 1 form2
        $this->assertSame(1, $summary['no_invoice']['students']); // seedBasics student
        $this->assertSame(110.0, $summary['collected']['amount']); // 90 paid + 20 partial
        $this->assertSame(70.0, $summary['outstanding']['amount']); // 180 - 110
        $defaultLang->assertSee('Xog kooban');
        $defaultLang->assertSee('Wadarta lacagta la soo ururiyey');
        $defaultLang->assertSee('Haray');

        $this->actingAs($finance)
            ->get(route('reports.fees.students-by-form', ['month' => 'not-a-month']))
            ->assertOk()
            ->assertViewHas('month', now()->format('Y-m'));

        $this->actingAs($finance)
            ->get(route('reports.fees.students-by-form', [
                'month' => $monthYm,
                'lang' => 'en',
            ]))
            ->assertOk()
            ->assertSee('Class')
            ->assertSee('Total students')
            ->assertSee('Students who paid')
            ->assertSee('Students with partial payment')
            ->assertSee('Students who did not pay')
            ->assertSee('TOTAL')
            ->assertSee('Form 1')
            ->assertDontSee('Fasalka');

        $this->actingAs($finance)
            ->get(route('reports.fees.students-by-form.print', [
                'month' => $monthYm,
                'lang' => 'so',
            ]))
            ->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertSee('Qaybta 1:')
            ->assertSee('WADARTA');
    }

    public function test_fee_income_report_splits_current_arrears_and_transfer(): void
    {
        ['finance' => $finance, 'class' => $class, 'student' => $student] = $this->seedBasics();
        $year = AcademicYear::current();
        $monthYm = now()->format('Y-m');
        $monthStart = now()->startOfMonth();
        $prevMonth = now()->startOfMonth()->subMonth();

        $transferStudent = Student::query()->create([
            'student_code' => 'STU-TR-1',
            'full_name' => 'Transfer Student',
            'dob' => '2010-05-01',
            'gender' => Gender::Male,
            'previous_school' => 'Another School',
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $transferStudent->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 20,
        ]);

        $currentInv = Invoice::query()->create([
            'invoice_number' => 'INV-INC-CUR',
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'billing_month' => $monthStart->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 45,
            'status' => InvoiceStatus::Paid,
        ]);
        $arrearsInv = Invoice::query()->create([
            'invoice_number' => 'INV-INC-ARR',
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'billing_month' => $prevMonth->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 30,
            'status' => InvoiceStatus::Partial,
        ]);
        $transferInv = Invoice::query()->create([
            'invoice_number' => 'INV-INC-TR',
            'student_id' => $transferStudent->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'billing_month' => $monthStart->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 45,
            'status' => InvoiceStatus::Paid,
        ]);

        $paidAt = $monthStart->copy()->addDays(5);
        Payment::query()->create([
            'invoice_id' => $currentInv->id,
            'student_id' => $student->id,
            'amount' => 45,
            'method' => PaymentMethod::Cash,
            'receipt_number' => 'RCP-INC-1',
            'paid_at' => $paidAt,
            'recorded_by' => $finance->id,
        ]);
        Payment::query()->create([
            'invoice_id' => $arrearsInv->id,
            'student_id' => $student->id,
            'amount' => 30,
            'method' => PaymentMethod::Cash,
            'receipt_number' => 'RCP-INC-2',
            'paid_at' => $paidAt,
            'recorded_by' => $finance->id,
        ]);
        Payment::query()->create([
            'invoice_id' => $transferInv->id,
            'student_id' => $transferStudent->id,
            'amount' => 45,
            'method' => PaymentMethod::Cash,
            'receipt_number' => 'RCP-INC-3',
            'paid_at' => $paidAt,
            'recorded_by' => $finance->id,
        ]);

        $response = $this->actingAs($finance)
            ->get(route('reports.fees.income', ['month' => $monthYm, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Income report')
            ->assertSee('Section 2: Income details')
            ->assertSee('Student fees (Forms 1–4)')
            ->assertSee('Prior months')
            ->assertSee('Transfer-in')
            ->assertSee('TOTAL INCOME')
            ->assertViewHas('lines')
            ->assertViewHas('total');

        $lines = collect($response->viewData('lines'))->keyBy('key');
        $this->assertSame(45.0, $lines['current']['amount']);
        $this->assertSame(30.0, $lines['arrears']['amount']);
        $this->assertSame(45.0, $lines['transfer']['amount']);
        $this->assertSame(120.0, (float) $response->viewData('total'));

        $this->actingAs($finance)
            ->get(route('reports.fees.income', ['month' => $monthYm]))
            ->assertOk()
            ->assertSee('WADARTA DAKHLIGA')
            ->assertSee('Lacagta ardayda');

        $this->actingAs($finance)
            ->get(route('reports.fees.income.print', ['month' => $monthYm, 'lang' => 'so']))
            ->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertSee('Qaybta 2:');
    }

    public function test_fee_expense_report_includes_payroll_and_categories(): void
    {
        ['finance' => $finance, 'admin' => $admin] = $this->seedBasics();
        $monthYm = now()->format('Y-m');
        $monthStart = now()->startOfMonth();

        $teacher = Staff::query()->create([
            'employee_code' => 'EMP-T-EXP',
            'full_name' => 'Teacher Exp',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'Math',
            'fixed_salary_usd' => 500,
            'status' => StaffStatus::Active,
        ]);
        $adminStaff = Staff::query()->create([
            'employee_code' => 'EMP-A-EXP',
            'full_name' => 'Admin Exp',
            'role_label' => StaffRoleLabel::Admin,
            'subject_specialty' => null,
            'fixed_salary_usd' => 300,
            'status' => StaffStatus::Active,
        ]);

        $run = PayrollRun::query()->create([
            'billing_month' => $monthStart->toDateString(),
            'status' => PayrollRunStatus::Confirmed,
            'staff_count' => 2,
            'total_amount' => 800,
            'generated_by' => $admin->id,
            'generated_at' => now(),
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);
        PayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'staff_id' => $teacher->id,
            'employee_code' => $teacher->employee_code,
            'full_name' => $teacher->full_name,
            'role_label' => 'Teacher',
            'salary_usd' => 500,
            'payslip_number' => 'PS-EXP-T',
        ]);
        PayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'staff_id' => $adminStaff->id,
            'employee_code' => $adminStaff->employee_code,
            'full_name' => $adminStaff->full_name,
            'role_label' => 'Admin',
            'salary_usd' => 300,
            'payslip_number' => 'PS-EXP-A',
        ]);

        $stationery = ExpenseCategory::query()->firstOrCreate(
            ['name' => 'Stationery'],
            ['is_active' => true]
        );
        Expense::query()->create([
            'expense_category_id' => $stationery->id,
            'expense_date' => $monthStart->copy()->addDays(3)->toDateString(),
            'amount' => 173,
            'payment_method' => PaymentMethod::Cash,
            'description' => 'Pens and paper',
            'recorded_by' => $finance->id,
        ]);

        $response = $this->actingAs($finance)
            ->get(route('reports.fees.expenses', ['month' => $monthYm, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Expense report')
            ->assertSee('Section 3: Expense details')
            ->assertSee('Teacher salaries')
            ->assertSee('Admin / non-teacher salaries')
            ->assertSee('Stationery')
            ->assertSee('TOTAL EXPENSES');

        $lines = collect($response->viewData('lines'))->keyBy('key');
        $this->assertSame(500.0, $lines['payroll_teachers']['amount']);
        $this->assertSame(300.0, $lines['payroll_admin']['amount']);
        $this->assertSame(973.0, (float) $response->viewData('total'));

        $this->actingAs($finance)
            ->get(route('reports.fees.expenses', ['month' => $monthYm]))
            ->assertOk()
            ->assertSee('WADARTA KHARASHKA')
            ->assertSee('Mushaharka macallimiinta');

        $this->actingAs($finance)
            ->get(route('reports.fees.expenses.print', ['month' => $monthYm, 'lang' => 'so']))
            ->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertSee('Qaybta 3:');
    }

    public function test_fee_net_income_report_combines_income_and_expenses(): void
    {
        ['finance' => $finance, 'admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedBasics();
        $year = AcademicYear::current();
        $monthYm = now()->format('Y-m');
        $monthStart = now()->startOfMonth();

        Invoice::query()->create([
            'invoice_number' => 'INV-R-NET',
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'billing_month' => $monthStart->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        Payment::query()->create([
            'invoice_id' => Invoice::query()->where('invoice_number', 'INV-R-NET')->value('id'),
            'student_id' => $student->id,
            'amount' => 200,
            'method' => PaymentMethod::Cash,
            'receipt_number' => 'RCP-NET-1',
            'paid_at' => $monthStart->copy()->addDays(2),
            'recorded_by' => $finance->id,
        ]);

        $teacher = Staff::query()->create([
            'employee_code' => 'EMP-T-NET',
            'full_name' => 'Teacher Net',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'Math',
            'fixed_salary_usd' => 400,
            'status' => StaffStatus::Active,
        ]);

        $run = PayrollRun::query()->create([
            'billing_month' => $monthStart->toDateString(),
            'status' => PayrollRunStatus::Confirmed,
            'staff_count' => 1,
            'total_amount' => 400,
            'generated_by' => $admin->id,
            'generated_at' => now(),
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);
        PayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'staff_id' => $teacher->id,
            'employee_code' => $teacher->employee_code,
            'full_name' => $teacher->full_name,
            'role_label' => 'Teacher',
            'salary_usd' => 400,
            'payslip_number' => 'PS-NET-T',
        ]);

        $utilities = ExpenseCategory::query()->firstOrCreate(['name' => 'Utilities'], ['is_active' => true]);
        Expense::query()->create([
            'expense_category_id' => $utilities->id,
            'expense_date' => $monthStart->copy()->addDays(4)->toDateString(),
            'amount' => 50,
            'payment_method' => PaymentMethod::Cash,
            'description' => 'Power bill',
            'recorded_by' => $finance->id,
        ]);

        $response = $this->actingAs($finance)
            ->get(route('reports.fees.net-income', ['month' => $monthYm, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Net income')
            ->assertSee('Section 2: Income details')
            ->assertSee('Section 3: Expense details')
            ->assertSee('Net income summary')
            ->assertSee('NET INCOME');

        $this->assertSame(200.0, (float) $response->viewData('incomeTotal'));
        $this->assertSame(450.0, (float) $response->viewData('expenseTotal'));
        $this->assertSame(-250.0, (float) $response->viewData('net'));

        $this->actingAs($finance)
            ->get(route('reports.fees.net-income', ['month' => $monthYm]))
            ->assertOk()
            ->assertSee('Dakhliga ah')
            ->assertSee('WADARTA DAKHLIGA AH');

        $this->actingAs($finance)
            ->get(route('reports.fees.net-income.print', ['month' => $monthYm, 'lang' => 'so']))
            ->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertSee('Qaybta 2:');
    }

    public function test_fee_monthly_close_report_combines_all_sections(): void
    {
        ['finance' => $finance, 'admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedBasics();
        $year = AcademicYear::current();
        $monthYm = now()->format('Y-m');
        $monthStart = now()->startOfMonth();

        Invoice::query()->create([
            'invoice_number' => 'INV-R-CLOSE',
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'billing_month' => $monthStart->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 45,
            'status' => InvoiceStatus::Paid,
        ]);

        Payment::query()->create([
            'invoice_id' => Invoice::query()->where('invoice_number', 'INV-R-CLOSE')->value('id'),
            'student_id' => $student->id,
            'amount' => 45,
            'method' => PaymentMethod::Cash,
            'receipt_number' => 'RCP-CLOSE-1',
            'paid_at' => $monthStart->copy()->addDays(2),
            'recorded_by' => $finance->id,
        ]);

        $teacher = Staff::query()->create([
            'employee_code' => 'EMP-T-CLOSE',
            'full_name' => 'Teacher Close',
            'role_label' => StaffRoleLabel::Teacher,
            'subject_specialty' => 'Math',
            'fixed_salary_usd' => 300,
            'status' => StaffStatus::Active,
        ]);

        $run = PayrollRun::query()->create([
            'billing_month' => $monthStart->toDateString(),
            'status' => PayrollRunStatus::Confirmed,
            'staff_count' => 1,
            'total_amount' => 300,
            'generated_by' => $admin->id,
            'generated_at' => now(),
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);
        PayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'staff_id' => $teacher->id,
            'employee_code' => $teacher->employee_code,
            'full_name' => $teacher->full_name,
            'role_label' => 'Teacher',
            'salary_usd' => 300,
            'payslip_number' => 'PS-CLOSE-T',
        ]);

        $response = $this->actingAs($finance)
            ->get(route('reports.fees.monthly-close', ['month' => $monthYm, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Monthly accounting close')
            ->assertSee('Section 1:')
            ->assertSee('Section 2:')
            ->assertSee('Section 3:')
            ->assertSee('Section 4:')
            ->assertSee('Section 5:')
            ->assertSee('Section 6:')
            ->assertSee('Profit / Loss');

        $this->assertSame(45.0, (float) $response->viewData('incomeTotal'));
        $this->assertSame(300.0, (float) $response->viewData('expenseTotal'));
        $this->assertSame(-255.0, (float) $response->viewData('net'));

        $this->actingAs($finance)
            ->get(route('reports.fees.monthly-close', ['month' => $monthYm]))
            ->assertOk()
            ->assertSee('Xisaab-xidhka bil\'le')
            ->assertSee('Qaybta 4:')
            ->assertSee('Faa\'iido / Khasaaro');

        $this->actingAs($finance)
            ->get(route('reports.fees.monthly-close.print', ['month' => $monthYm, 'lang' => 'so']))
            ->assertOk()
            ->assertSee('Print / Save PDF')
            ->assertSee('Xisaabiyaha');
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

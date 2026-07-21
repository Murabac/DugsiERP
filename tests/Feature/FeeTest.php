<?php

namespace Tests\Feature;

use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\FeeCalculator;
use App\Support\Money;
use App\Support\MonthlyInvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SchoolSetting::set('monthly_fee_usd', '45');
        SchoolSetting::set('sibling_discount_percent', '10');
        SchoolSetting::set('need_based_discount_percent', '20');
        FeeCalculator::clearSiblingCache();
    }

    /**
     * @return array{admin: User, finance: User, class: SchoolClass, student: Student}
     */
    private function seedBasics(): array
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();
        $year = AcademicYear::current();

        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 40,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-FEE1',
            'full_name' => 'Ayaan Cabdi',
            'dob' => '2011-01-01',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
            'need_based_discount_amount' => 0,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->subMonths(2)->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        Guardian::query()->create([
            'student_id' => $student->id,
            'full_name' => 'Cabdi Parent',
            'phone' => '+252634111111',
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
        ]);

        return compact('admin', 'finance', 'class', 'student');
    }

    public function test_money_formats_usd(): void
    {
        $this->assertSame('$45.00', Money::format(45));
        $this->assertSame('$45.50', Money::format(45.5));
        $this->assertSame('0%', Money::formatPercent(0));
        $this->assertSame('0.35%', Money::formatPercent(0.35));
        $this->assertSame('3%', Money::formatPercent(3));
        $this->assertSame('13.5%', Money::formatPercent(13.5));
    }

    public function test_admin_can_save_school_fee_settings_finance_cannot(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();

        $this->actingAs($admin)
            ->post(route('settings.fee-settings'), [
                'monthly_fee_usd' => 50,
                'transport_fee_usd' => 15,
                'sibling_discount_percent' => 15,
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'fees']));

        $this->assertSame(50.0, SchoolSetting::monthlyFeeUsd());
        $this->assertSame(15.0, SchoolSetting::transportFeeUsd());
        $this->assertSame(15, SchoolSetting::siblingDiscountPercent());

        $this->actingAs($finance)
            ->post(route('settings.fee-settings'), [
                'monthly_fee_usd' => 99,
                'transport_fee_usd' => 15,
                'sibling_discount_percent' => 0,
            ])
            ->assertForbidden();
    }

    public function test_generate_creates_same_base_fee_for_all_forms(): void
    {
        ['finance' => $finance, 'student' => $student] = $this->seedBasics();
        $year = AcademicYear::current();

        $form4 = SchoolClass::query()->create([
            'form_level' => 4,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 40,
            'status' => ClassStatus::Active,
            'room' => 'R-4A',
        ]);
        $senior = Student::query()->create([
            'student_code' => 'STU-FEE4',
            'full_name' => 'Form Four Student',
            'dob' => '2008-01-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $senior->id,
            'class_id' => $form4->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        $this->artisan('fees:generate-monthly')->assertSuccessful();

        $this->assertDatabaseHas('invoices', [
            'student_id' => $student->id,
            'base_amount' => 45,
            'amount_due' => 45,
        ]);
        $this->assertDatabaseHas('invoices', [
            'student_id' => $senior->id,
            'base_amount' => 45,
            'amount_due' => 45,
        ]);

        // GET must not create invoices.
        Invoice::query()->delete();
        $this->actingAs($finance)
            ->get(route('finance.fee-collection'))
            ->assertOk()
            ->assertSee('Create month invoices');

        $this->assertDatabaseMissing('invoices', ['student_id' => $student->id]);

        $this->actingAs($finance)
            ->post(route('finance.fee-collection.generate'), ['month' => now()->format('Y-m')])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', ['student_id' => $student->id]);
    }

    public function test_cannot_generate_future_month(): void
    {
        ['finance' => $finance] = $this->seedBasics();
        $future = now()->addMonth()->format('Y-m');

        $this->actingAs($finance)
            ->from(route('finance.fee-collection'))
            ->post(route('finance.fee-collection.generate'), ['month' => $future])
            ->assertRedirect()
            ->assertSessionHasErrors('month');

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_sibling_discount_applies_to_later_sibling(): void
    {
        ['class' => $class, 'student' => $first] = $this->seedBasics();
        $year = AcademicYear::current();

        $second = Student::query()->create([
            'student_code' => 'STU-FEE2',
            'full_name' => 'Second Sibling',
            'dob' => '2012-02-02',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $second->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->subMonth()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 2,
        ]);
        Guardian::query()->create([
            'student_id' => $second->id,
            'full_name' => 'Cabdi Parent',
            'phone' => '+252634111111',
            'relationship' => GuardianRelationship::Father,
            'is_primary' => true,
        ]);

        FeeCalculator::clearSiblingCache();
        $first->load('primaryGuardian');
        $second->load('primaryGuardian');

        $q1 = FeeCalculator::quote($first, $class, $year);
        $q2 = FeeCalculator::quote($second, $class, $year);

        $this->assertSame(45.0, $q1['due']);
        $this->assertSame(40.5, $q2['due']);
    }

    public function test_need_based_discount(): void
    {
        ['class' => $class, 'student' => $student] = $this->seedBasics();
        $student->update(['need_based_discount_amount' => 9]);
        FeeCalculator::clearSiblingCache();

        $quote = FeeCalculator::quote($student->fresh(), $class, AcademicYear::current());
        $this->assertSame(36.0, $quote['due']);
    }

    public function test_full_discount_invoice_is_paid_status(): void
    {
        ['student' => $student, 'class' => $class] = $this->seedBasics();
        $student->update(['need_based_discount_amount' => 45]);
        FeeCalculator::clearSiblingCache();

        MonthlyInvoiceGenerator::generate(now()->startOfMonth());

        $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(0.0, (float) $invoice->amount_due);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_payment_updates_invoice_status_and_opens_receipt(): void
    {
        ['finance' => $finance, 'student' => $student] = $this->seedBasics();

        $this->artisan('fees:generate-monthly')->assertSuccessful();

        $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();

        $this->actingAs($finance)
            ->post(route('finance.payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 20,
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect();

        $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertStringStartsWith('RCP-', $payment->receipt_number);

        $this->actingAs($finance)
            ->get(route('finance.payments.receipt', $payment))
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertSee('OFFICIAL RECEIPT');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);

        $this->actingAs($finance)
            ->post(route('finance.payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 25,
                'method' => PaymentMethod::MobileMoney->value,
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_payment_cannot_exceed_balance(): void
    {
        ['finance' => $finance, 'student' => $student] = $this->seedBasics();
        $this->artisan('fees:generate-monthly')->assertSuccessful();
        $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();

        $this->actingAs($finance)
            ->from(route('finance.fee-collection'))
            ->post(route('finance.payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 100,
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_fee_collection_get_does_not_auto_create_invoices(): void
    {
        ['finance' => $finance, 'student' => $student] = $this->seedBasics();

        $this->actingAs($finance)
            ->get(route('finance.fee-collection'))
            ->assertOk();

        $this->assertDatabaseMissing('invoices', ['student_id' => $student->id]);
    }

    public function test_teacher_cannot_access_fee_collection(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($teacher)
            ->get(route('finance.fee-collection'))
            ->assertForbidden();
    }

    public function test_admin_can_set_student_need_based_percent_and_revises_unpaid_invoice(): void
    {
        ['admin' => $admin, 'student' => $student] = $this->seedBasics();
        $this->artisan('fees:generate-monthly')->assertSuccessful();

        $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(45.0, (float) $invoice->amount_due);

        $this->actingAs($admin)
            ->post(route('students.need-based', $student), [
                'need_based_discount_amount' => 9,
            ])
            ->assertRedirect();

        $this->assertSame(9.0, (float) $student->fresh()->need_based_discount_amount);
        $invoice->refresh();
        $this->assertSame(36.0, (float) $invoice->amount_due);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
    }

    public function test_lump_payment_allocates_oldest_unpaid_months_first(): void
    {
        ['finance' => $finance, 'student' => $student, 'class' => $class] = $this->seedBasics();
        $year = AcademicYear::current();

        $older = now()->startOfMonth()->subMonths(2);
        $newer = now()->startOfMonth()->subMonth();

        foreach ([$older, $newer] as $i => $billingMonth) {
            Invoice::query()->create([
                'invoice_number' => 'INV-TEST-'.($i + 1),
                'student_id' => $student->id,
                'class_id' => $class->id,
                'academic_year' => $year,
                'billing_month' => $billingMonth->toDateString(),
                'base_amount' => 45,
                'discount_applied' => 0,
                'discount_reason' => null,
                'amount_due' => 45,
                'amount_paid' => 0,
                'status' => InvoiceStatus::Unpaid,
            ]);
        }

        $anchor = Invoice::query()->where('invoice_number', 'INV-TEST-2')->firstOrFail();

        $this->actingAs($finance)
            ->post(route('finance.payments.store'), [
                'invoice_id' => $anchor->id,
                'amount' => 60,
                'method' => PaymentMethod::Cash->value,
                'allocate_arrears' => '1',
            ])
            ->assertRedirect();

        $oldInv = Invoice::query()->where('invoice_number', 'INV-TEST-1')->firstOrFail();
        $newInv = Invoice::query()->where('invoice_number', 'INV-TEST-2')->firstOrFail();

        $this->assertSame(InvoiceStatus::Paid, $oldInv->status);
        $this->assertSame(45.0, (float) $oldInv->amount_paid);
        $this->assertSame(InvoiceStatus::Partial, $newInv->status);
        $this->assertSame(15.0, (float) $newInv->amount_paid);

        $receipt = Payment::query()->where('student_id', $student->id)->value('receipt_number');
        $this->assertSame(2, Payment::query()->where('receipt_number', $receipt)->count());
    }

    public function test_this_month_only_does_not_touch_older_invoices(): void
    {
        ['finance' => $finance, 'student' => $student, 'class' => $class] = $this->seedBasics();
        $year = AcademicYear::current();

        Invoice::query()->create([
            'invoice_number' => 'INV-OLD-1',
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'billing_month' => now()->startOfMonth()->subMonth()->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $this->artisan('fees:generate-monthly')->assertSuccessful();
        $current = Invoice::query()
            ->where('student_id', $student->id)
            ->whereDate('billing_month', now()->startOfMonth()->toDateString())
            ->firstOrFail();

        $this->actingAs($finance)
            ->post(route('finance.payments.store'), [
                'invoice_id' => $current->id,
                'amount' => 45,
                'method' => PaymentMethod::Cash->value,
                'allocate_arrears' => '0',
            ])
            ->assertRedirect();

        $this->assertSame(InvoiceStatus::Unpaid, Invoice::query()->where('invoice_number', 'INV-OLD-1')->firstOrFail()->status);
        $this->assertSame(InvoiceStatus::Paid, $current->fresh()->status);
    }

    public function test_fees_dashboard_shows_live_invoice_totals(): void
    {
        ['finance' => $finance, 'student' => $student] = $this->seedBasics();
        $this->artisan('fees:generate-monthly')->assertSuccessful();

        $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();
        $this->actingAs($finance)
            ->post(route('finance.payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 20,
                'method' => PaymentMethod::Cash->value,
                'allocate_arrears' => '0',
            ])
            ->assertRedirect();

        $this->actingAs($finance)
            ->get(route('finance.fees-dashboard'))
            ->assertOk()
            ->assertSee('Fees Dashboard')
            ->assertSee(Money::format(20), false)
            ->assertSee(Money::format(45), false)
            ->assertSee($student->full_name)
            ->assertSee('Recent Payments');
    }

    public function test_fee_settings_revise_unpaid_invoices(): void
    {
        ['admin' => $admin, 'student' => $student] = $this->seedBasics();
        $this->artisan('fees:generate-monthly')->assertSuccessful();

        $this->actingAs($admin)
            ->post(route('settings.fee-settings'), [
                'monthly_fee_usd' => 60,
                'transport_fee_usd' => 15,
                'sibling_discount_percent' => 10,
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(60.0, (float) $invoice->amount_due);
    }
}

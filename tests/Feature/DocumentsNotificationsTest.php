<?php

namespace Tests\Feature;

use App\Enums\AcademicTerm;
use App\Enums\ClassStatus;
use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\LetterGrade;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\DocumentLog;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\TelesomSmsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentsNotificationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{admin: User, finance: User, class: SchoolClass, form4: SchoolClass, student: Student, form4Student: Student}
     */
    private function seedBasics(): array
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();
        $year = AcademicYear::current();

        $class = SchoolClass::query()->create([
            'form_level' => 2,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-2A',
            'status' => ClassStatus::Active,
        ]);

        $form4 = SchoolClass::query()->create([
            'form_level' => 4,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-4A',
            'status' => ClassStatus::Active,
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-901',
            'full_name' => 'Doc Student',
            'dob' => '2010-01-01',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);

        $form4Student = Student::query()->create([
            'student_code' => 'STU-904',
            'full_name' => 'Form Four Student',
            'dob' => '2008-01-01',
            'gender' => Gender::Male,
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

        Enrollment::query()->create([
            'student_id' => $form4Student->id,
            'class_id' => $form4->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        foreach ([
            [NotificationType::AbsenceAlert, 'Absence Alert', 'Absent: {student_name} {class} {date}', ['student_name', 'class', 'date']],
            [NotificationType::FeeReminder, 'Fee Due Reminder', 'Fee due {student_name} {amount} {due_date}', ['student_name', 'amount', 'due_date']],
            [NotificationType::FeeOverdue, 'Fee Overdue Notice', 'Overdue {student_name} {amount} {days}', ['student_name', 'amount', 'days']],
        ] as [$type, $name, $body, $vars]) {
            NotificationTemplate::query()->updateOrCreate(
                ['type' => $type],
                [
                    'name' => $name,
                    'channel' => 'sms',
                    'body' => $body,
                    'variables' => $vars,
                    'is_active' => true,
                ],
            );
        }

        return compact('admin', 'finance', 'class', 'form4', 'student', 'form4Student');
    }

    public function test_finance_can_generate_id_card_and_history(): void
    {
        ['finance' => $finance, 'class' => $class, 'student' => $student] = $this->seedBasics();

        $this->actingAs($finance)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Generate');

        $this->actingAs($finance)
            ->post(route('documents.store'), [
                'document_type' => DocumentType::StudentIdCard->value,
                'student_id' => $student->id,
                'class_id' => $class->id,
            ])
            ->assertRedirect();

        $doc = DocumentLog::query()->firstOrFail();
        $this->assertSame(DocumentType::StudentIdCard, $doc->document_type);
        $this->assertStringStartsWith('DOC-', $doc->document_number);

        $this->actingAs($finance)
            ->get(route('documents.print', $doc))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee($doc->document_number);

        $this->actingAs($finance)
            ->get(route('documents.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee($doc->document_number);
    }

    public function test_document_preview_returns_real_marks(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedBasics();
        $year = AcademicYear::current();
        $subject = Subject::query()->create(['name' => 'Mathematics', 'sort_order' => 1]);

        Grade::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'term' => AcademicTerm::Term1,
            'academic_year' => $year,
            'score_percent' => 91.5,
            'letter_grade' => LetterGrade::A,
            'entered_by' => $admin->id,
            'remarks' => 'Excellent',
        ]);

        $this->actingAs($admin)
            ->getJson(route('documents.preview', [
                'document_type' => DocumentType::ReportCard->value,
                'student_id' => $student->id,
                'class_id' => $class->id,
                'term' => AcademicTerm::Term1->value,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('student.name', 'Doc Student')
            ->assertJsonPath('rows.0.subject', 'Mathematics')
            ->assertJsonPath('rows.0.score', 91.5)
            ->assertJsonPath('rows.0.letter', 'A')
            ->assertJsonPath('rows.0.remarks', 'Excellent');
    }

    public function test_finance_cannot_preview_or_store_academic_documents(): void
    {
        ['finance' => $finance, 'class' => $class, 'student' => $student] = $this->seedBasics();

        $this->actingAs($finance)
            ->getJson(route('documents.preview', [
                'document_type' => DocumentType::ReportCard->value,
                'student_id' => $student->id,
                'class_id' => $class->id,
                'term' => AcademicTerm::Term1->value,
            ]))
            ->assertForbidden();

        $this->actingAs($finance)
            ->post(route('documents.store'), [
                'document_type' => DocumentType::ReportCard->value,
                'student_id' => $student->id,
                'class_id' => $class->id,
                'term' => AcademicTerm::Term1->value,
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertViewHas('types', function ($types) {
                $values = collect($types)->map(fn ($t) => $t->value)->all();

                return $values === [
                    DocumentType::FeeReceipt->value,
                    DocumentType::StudentIdCard->value,
                ];
            });
    }

    public function test_document_preview_requires_active_enrollment(): void
    {
        ['finance' => $finance, 'class' => $class, 'form4' => $form4, 'student' => $student] = $this->seedBasics();

        $this->actingAs($finance)
            ->getJson(route('documents.preview', [
                'document_type' => DocumentType::StudentIdCard->value,
                'student_id' => $student->id,
                'class_id' => $form4->id,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonFragment(['message' => 'Student is not actively enrolled in the selected class for '.AcademicYear::current().'.']);
    }

    public function test_document_generate_is_idempotent_within_window(): void
    {
        ['finance' => $finance, 'class' => $class, 'student' => $student] = $this->seedBasics();

        $payload = [
            'document_type' => DocumentType::StudentIdCard->value,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'intent' => 'print',
        ];

        $this->actingAs($finance)->post(route('documents.store'), $payload)->assertRedirect();
        $this->actingAs($finance)->post(route('documents.store'), $payload)->assertRedirect();
        $this->actingAs($finance)->post(route('documents.store'), array_merge($payload, ['intent' => 'pdf']))
            ->assertRedirect();

        $this->assertSame(1, DocumentLog::query()->count());
        $firstNumber = DocumentLog::query()->value('document_number');

        $this->actingAs($finance)
            ->post(route('documents.store'), array_merge($payload, ['force' => '1']))
            ->assertRedirect();

        $this->assertSame(2, DocumentLog::query()->count());
        $this->assertNotSame($firstNumber, DocumentLog::query()->latest('id')->value('document_number'));
    }

    public function test_pdf_intent_opens_print_with_autoprint(): void
    {
        ['finance' => $finance, 'class' => $class, 'student' => $student] = $this->seedBasics();

        $response = $this->actingAs($finance)
            ->post(route('documents.store'), [
                'document_type' => DocumentType::StudentIdCard->value,
                'student_id' => $student->id,
                'class_id' => $class->id,
                'intent' => 'pdf',
            ]);

        $doc = DocumentLog::query()->firstOrFail();
        $response->assertRedirect(route('documents.print', ['document' => $doc, 'autoprint' => 1]));

        $this->actingAs($finance)
            ->get(route('documents.print', ['document' => $doc, 'autoprint' => 1]))
            ->assertOk()
            ->assertSee('window.print', false);
    }

    public function test_document_store_validation_returns_to_generate_tab(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedBasics();

        $this->actingAs($admin)
            ->from(route('documents.index'))
            ->post(route('documents.store'), [
                'document_type' => DocumentType::CertificateCompletion->value,
                'student_id' => $student->id,
                'class_id' => $class->id,
            ])
            ->assertRedirect(route('documents.index', ['tab' => 'generate']))
            ->assertSessionHasErrors('class_id');
    }

    public function test_report_card_and_certificate_and_transfer(): void
    {
        ['admin' => $admin, 'class' => $class, 'form4' => $form4, 'student' => $student, 'form4Student' => $form4Student] = $this->seedBasics();

        $this->actingAs($admin)
            ->post(route('documents.store'), [
                'document_type' => DocumentType::ReportCard->value,
                'student_id' => $student->id,
                'class_id' => $class->id,
                'term' => AcademicTerm::Term1->value,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('documents.print', DocumentLog::query()->latest('id')->firstOrFail()))
            ->assertOk()
            ->assertSee('Grade Report Card');

        $this->actingAs($admin)
            ->post(route('documents.store'), [
                'document_type' => DocumentType::CertificateCompletion->value,
                'student_id' => $form4Student->id,
                'class_id' => $form4->id,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('documents.print', DocumentLog::query()->latest('id')->firstOrFail()))
            ->assertOk()
            ->assertSee('Certificate of Completion');

        $this->actingAs($admin)
            ->post(route('documents.store'), [
                'document_type' => DocumentType::TransferCertificate->value,
                'student_id' => $student->id,
                'class_id' => $class->id,
                'reason' => 'Family relocation',
                'date_of_leaving' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('documents.print', DocumentLog::query()->latest('id')->firstOrFail()))
            ->assertOk()
            ->assertSee('Transfer Certificate')
            ->assertSee('Family relocation');
    }

    public function test_fee_receipt_document_from_payment(): void
    {
        ['finance' => $finance, 'class' => $class, 'student' => $student] = $this->seedBasics();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-2026-07-0001',
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'billing_month' => now()->startOfMonth()->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 45,
            'status' => InvoiceStatus::Paid,
        ]);

        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'amount' => 45,
            'method' => PaymentMethod::Cash,
            'receipt_number' => 'RCP-20260716-0001',
            'paid_at' => now(),
            'recorded_by' => $finance->id,
            'notes' => null,
        ]);

        $this->actingAs($finance)
            ->post(route('documents.store'), [
                'document_type' => DocumentType::FeeReceipt->value,
                'student_id' => $student->id,
                'payment_id' => $payment->id,
            ])
            ->assertRedirect();

        $this->actingAs($finance)
            ->get(route('documents.print', DocumentLog::query()->firstOrFail()))
            ->assertOk()
            ->assertSee('Fee Receipt')
            ->assertSee('RCP-20260716-0001');
    }

    public function test_teacher_forbidden_from_documents_and_notifications(): void
    {
        $this->seedBasics();
        $teacher = User::factory()->role(UserRole::Teacher)->create();

        $this->actingAs($teacher)->get(route('documents.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('notifications.index'))->assertForbidden();
    }

    public function test_notifications_templates_and_fee_reminder_without_gateway(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedBasics();

        $student->guardians()->create([
            'full_name' => 'Parent One',
            'phone' => '+252634009999',
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Absence Alert')
            ->assertSee('configured');

        $template = NotificationTemplate::query()->where('type', NotificationType::AbsenceAlert)->firstOrFail();
        $this->actingAs($admin)
            ->post(route('notifications.templates.update', $template), [
                'body' => 'Updated absence for {student_name} on {date} ({class}).',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('Updated absence for {student_name} on {date} ({class}).', $template->fresh()->body);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-2026-07-0099',
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'billing_month' => now()->startOfMonth()->subMonth()->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $this->actingAs($admin)
            ->post(route('notifications.fee-reminder'), [
                'invoice_id' => $invoice->id,
                'kind' => 'reminder',
            ])
            ->assertRedirect();

        $log = NotificationLog::query()->where('type', NotificationType::FeeReminder)->firstOrFail();
        $this->assertSame(NotificationStatus::Failed, $log->status);
        $this->assertStringContainsString('Doc Student', $log->message_body);
        $this->assertSame((int) $invoice->id, (int) $log->related_invoice_id);
    }

    public function test_fee_reminder_cooldown_blocks_repeat_send(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedBasics();

        \App\Models\Guardian::query()->create([
            'student_id' => $student->id,
            'full_name' => 'Fee Parent',
            'phone' => '+252634999001',
            'relationship' => \App\Enums\GuardianRelationship::Father,
            'is_primary' => true,
        ]);

        config([
            'services.sms.driver' => 'telesom',
            'services.telesom.api_url' => 'https://sms.example.test/send',
            'services.telesom.username' => 'user',
            'services.telesom.password' => 'pass',
            'services.telesom.sender' => 'DUGSI',
            'services.telesom.secret' => 'secret',
            'services.textbee.api_key' => null,
            'services.textbee.device_id' => null,
        ]);

        Http::fake([
            'sms.example.test/*' => Http::response('OK', 200),
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-COOL-1',
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => AcademicYear::current(),
            'billing_month' => now()->startOfMonth()->toDateString(),
            'base_amount' => 45,
            'discount_applied' => 0,
            'discount_reason' => null,
            'amount_due' => 45,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $this->actingAs($admin)
            ->post(route('notifications.fee-reminder'), [
                'invoice_id' => $invoice->id,
                'kind' => 'reminder',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(1, NotificationLog::query()->where('type', NotificationType::FeeReminder)->count());
        $this->assertSame(NotificationStatus::Sent, NotificationLog::query()->where('type', NotificationType::FeeReminder)->first()->status);

        $this->actingAs($admin)
            ->post(route('notifications.fee-reminder'), [
                'invoice_id' => $invoice->id,
                'kind' => 'reminder',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'already sent'));

        $this->assertSame(1, NotificationLog::query()->where('type', NotificationType::FeeReminder)->count());
    }

    public function test_telesom_send_marks_sent_when_gateway_ok(): void
    {
        config([
            'services.sms.driver' => 'telesom',
            'services.telesom.api_url' => 'https://sms.example.test/send',
            'services.telesom.username' => 'user',
            'services.telesom.password' => 'pass',
            'services.telesom.sender' => 'DUGSI',
            'services.telesom.secret' => 'secret',
            'services.textbee.api_key' => null,
            'services.textbee.device_id' => null,
        ]);

        Http::fake([
            'sms.example.test/*' => Http::response(['status' => 'success', 'msg' => 'Message delivered'], 200),
        ]);

        $this->assertTrue(TelesomSmsClient::isConfigured());
        $result = TelesomSmsClient::send('+252634001234', 'Hello parent');
        $this->assertTrue($result['ok']);
    }

    public function test_textbee_send_marks_sent_when_gateway_ok(): void
    {
        config([
            'services.sms.driver' => 'textbee',
            'services.textbee.api_base' => 'https://api.textbee.test/api/v1',
            'services.textbee.api_key' => 'test-key',
            'services.textbee.device_id' => 'device-123',
        ]);

        Http::fake([
            'api.textbee.test/*' => Http::response(['success' => true, 'data' => ['id' => 'msg-1']], 200),
        ]);

        $this->assertTrue(\App\Support\SmsGateway::isConfigured());
        $this->assertSame('textbee', \App\Support\SmsGateway::driver());

        $result = \App\Support\SmsGateway::send('+252634001234', 'Hello from TextBee');
        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/gateway/devices/device-123/send-sms')
                && $request->hasHeader('x-api-key', 'test-key')
                && $request['recipients'] === ['+252634001234']
                && $request['message'] === 'Hello from TextBee';
        });
    }
}

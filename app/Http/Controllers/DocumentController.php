<?php

namespace App\Http\Controllers;

use App\Enums\AcademicTerm;
use App\Enums\ClassStatus;
use App\Enums\DocumentType;
use App\Enums\StudentStatus;
use App\Models\DocumentLog;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Support\AcademicYear;
use App\Support\DocumentIssuer;
use App\Support\GradeReport;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'generate');
        if (! in_array($tab, ['generate', 'history'], true)) {
            $tab = 'generate';
        }

        $year = AcademicYear::current();
        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $year)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        $students = Student::query()
            ->where('status', StudentStatus::Active)
            ->with(['enrollments' => fn ($q) => $q
                ->where('academic_year', $year)
                ->where('status', StudentStatus::Active)
                ->orderByDesc('id')
                ->select(['id', 'student_id', 'class_id', 'status', 'academic_year'])])
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'full_name', 'student_code']);

        $historyQuery = DocumentLog::query()
            ->with(['student', 'generatedBy'])
            ->latest('generated_at');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $historyQuery->where(function ($query) use ($q) {
                $query->where('document_number', 'like', '%'.$q.'%')
                    ->orWhereHas('student', fn ($s) => $s->where('full_name', 'like', '%'.$q.'%'));
            });
        }

        $history = $historyQuery->limit(100)->get();

        return view('documents.index', [
            'tab' => $tab,
            'academicYear' => $year,
            'classes' => $classes,
            'students' => $students,
            'types' => DocumentType::forUser($request->user()),
            'terms' => AcademicTerm::options(),
            'history' => $history,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'term' => ['nullable', Rule::enum(AcademicTerm::class)],
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'date_of_leaving' => ['nullable', 'date'],
            'conduct' => ['nullable', 'string', 'max:120'],
            'academic_progress' => ['nullable', 'string', 'max:120'],
        ]);

        $type = $data['document_type'] instanceof DocumentType
            ? $data['document_type']
            : DocumentType::from($data['document_type']);

        abort_unless($type->allowedFor($request->user()), 403);

        $student = Student::query()
            ->with('primaryGuardian')
            ->findOrFail($data['student_id']);

        $year = AcademicYear::current();
        $classId = (int) ($data['class_id'] ?? 0);
        $schoolClass = $classId > 0 ? SchoolClass::query()->find($classId) : null;

        if (! $schoolClass) {
            return response()->json([
                'ok' => false,
                'message' => 'Select a class and student to preview.',
            ]);
        }

        $enrollment = DocumentIssuer::activeEnrollment($student, $schoolClass, $year);
        if (! $enrollment && $type !== DocumentType::FeeReceipt) {
            return response()->json([
                'ok' => false,
                'message' => 'Student is not actively enrolled in the selected class for '.$year.'.',
            ]);
        }

        if ($type === DocumentType::CertificateCompletion && (int) $schoolClass->form_level !== 4) {
            return response()->json([
                'ok' => false,
                'message' => 'Certificate of Completion is for Form 4 students.',
            ]);
        }

        $payload = [
            'ok' => true,
            'type' => $type->value,
            'school_name' => SchoolSetting::schoolName(),
            'school_sub' => SchoolSetting::schoolLetterheadSub(),
            'academic_year' => $year,
            'student' => [
                'name' => $student->full_name,
                'code' => $student->student_code,
                'initials' => $student->initials(),
            ],
            'class' => $schoolClass->displayName(),
            'form_level' => $schoolClass->form_level,
            'roll' => $enrollment?->roll_number !== null
                ? str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT)
                : null,
            'guardian' => $student->primaryGuardian?->full_name,
        ];

        if ($type === DocumentType::ReportCard) {
            if (empty($data['term'])) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Select class, student, and term to preview real marks.',
                ]);
            }

            $term = $data['term'] instanceof AcademicTerm
                ? $data['term']
                : AcademicTerm::from($data['term']);
            $report = GradeReport::for($student, $schoolClass, $term, $year);

            $payload['term'] = $term->label();
            $payload['attendance_rate'] = $report['attendance_rate'];
            $payload['average'] = $report['average'];
            $payload['average_letter'] = $report['average_letter']?->value;
            $payload['rank'] = $report['rank'];
            $payload['class_size'] = $report['class_size'];
            $payload['rows'] = $report['rows']->map(fn ($row) => [
                'subject' => $row['subject']->name,
                'score' => $row['score'],
                'letter' => $row['letter']?->value,
                'remarks' => $row['remarks'],
            ])->values()->all();
        }

        if ($type === DocumentType::TransferCertificate) {
            $payload['reason'] = trim((string) ($data['reason'] ?? '')) ?: '—';
            $payload['conduct'] = trim((string) ($data['conduct'] ?? 'Good')) ?: 'Good';
            $payload['academic_progress'] = trim((string) ($data['academic_progress'] ?? 'Satisfactory')) ?: 'Satisfactory';
            $payload['date_of_leaving'] = $data['date_of_leaving'] ?? now()->toDateString();
        }

        if ($type === DocumentType::FeeReceipt) {
            $paymentId = (int) ($data['payment_id'] ?? 0);
            $payment = $paymentId > 0
                ? Payment::query()->with('invoice.schoolClass')->where('student_id', $student->id)->find($paymentId)
                : Payment::query()->with('invoice.schoolClass')->where('student_id', $student->id)->latest('paid_at')->first();

            if (! $payment) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No payment found for this student.',
                    'student' => $payload['student'],
                ]);
            }

            $payload['amount'] = Money::format($payment->amount);
            $payload['receipt_number'] = $payment->receipt_number;
            $payload['method'] = $payment->method?->label() ?? (string) $payment->method;
            $payload['paid_at'] = $payment->paid_at?->format('j M Y');
            $payload['class'] = $payment->invoice?->schoolClass?->displayName() ?? $payload['class'];
        }

        return response()->json($payload);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'term' => ['nullable', Rule::enum(AcademicTerm::class)],
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'date_of_leaving' => ['nullable', 'date'],
            'conduct' => ['nullable', 'string', 'max:120'],
            'academic_progress' => ['nullable', 'string', 'max:120'],
            'force' => ['sometimes', 'boolean'],
            'intent' => ['nullable', Rule::in(['print', 'pdf'])],
        ]);

        $intent = $data['intent'] ?? 'print';
        unset($data['intent']);
        $data['force'] = $request->boolean('force');

        $type = $data['document_type'] instanceof DocumentType
            ? $data['document_type']
            : DocumentType::from((string) $data['document_type']);
        abort_unless($type->allowedFor($request->user()), 403);

        try {
            $doc = DocumentIssuer::issue($data, $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('documents.index', ['tab' => 'generate'])
                ->withErrors($e->errors())
                ->withInput();
        }

        $printParams = ['document' => $doc];
        if ($intent === 'pdf') {
            $printParams['autoprint'] = 1;
        }

        return redirect()
            ->route('documents.print', $printParams)
            ->with('status', $doc->document_type->label().' ready ('.$doc->document_number.').');
    }

    public function print(Request $request, DocumentLog $document): View
    {
        $document->load(['student.primaryGuardian', 'schoolClass', 'payment.invoice.schoolClass', 'payment.recordedBy', 'generatedBy']);

        $student = $document->student;
        abort_unless($student !== null, 404);
        abort_unless($document->document_type->allowedFor($request->user()), 403);

        $schoolName = SchoolSetting::schoolName();
        $schoolLetterheadSub = SchoolSetting::schoolLetterheadSub();
        $year = (string) ($document->meta['academic_year'] ?? AcademicYear::current());
        $autoPrint = $request->boolean('autoprint');

        return match ($document->document_type) {
            DocumentType::ReportCard => $this->printReportCard($document, $student, $schoolName, $schoolLetterheadSub, $year, $autoPrint),
            DocumentType::CertificateCompletion => view('documents.print.certificate', [
                'document' => $document,
                'student' => $student,
                'schoolClass' => $document->schoolClass,
                'schoolName' => $schoolName,
                'schoolLetterheadSub' => $schoolLetterheadSub,
                'academicYear' => $year,
                'autoPrint' => $autoPrint,
            ]),
            DocumentType::TransferCertificate => view('documents.print.transfer', [
                'document' => $document,
                'student' => $student,
                'schoolClass' => $document->schoolClass,
                'schoolName' => $schoolName,
                'schoolLetterheadSub' => $schoolLetterheadSub,
                'meta' => $document->meta ?? [],
                'autoPrint' => $autoPrint,
            ]),
            DocumentType::FeeReceipt => $this->printFeeReceipt($document, $student, $schoolName, $schoolLetterheadSub, $autoPrint),
            DocumentType::StudentIdCard => view('documents.print.id-card', [
                'document' => $document,
                'student' => $student,
                'schoolClass' => $document->schoolClass,
                'schoolName' => $schoolName,
                'schoolLetterheadSub' => $schoolLetterheadSub,
                'academicYear' => $year,
                'autoPrint' => $autoPrint,
            ]),
        };
    }

    private function printReportCard(
        DocumentLog $document,
        Student $student,
        string $schoolName,
        string $schoolLetterheadSub,
        string $year,
        bool $autoPrint = false,
    ): View {
        $schoolClass = $document->schoolClass;
        abort_unless($schoolClass !== null && $document->term, 404);
        $term = AcademicTerm::from($document->term);

        $enrollment = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('class_id', $schoolClass->id)
            ->where('academic_year', $year)
            ->first();
        abort_unless($enrollment !== null, 404);

        $report = GradeReport::for($student, $schoolClass, $term, $year);

        return view('documents.print.report-card', [
            'document' => $document,
            'schoolClass' => $schoolClass,
            'student' => $student,
            'enrollment' => $enrollment,
            'term' => $term,
            'academicYear' => $year,
            'report' => $report,
            'issuedAt' => $document->generated_at,
            'schoolName' => $schoolName,
            'schoolLetterheadSub' => $schoolLetterheadSub,
            'autoPrint' => $autoPrint,
        ]);
    }

    private function printFeeReceipt(
        DocumentLog $document,
        Student $student,
        string $schoolName,
        string $schoolLetterheadSub,
        bool $autoPrint = false,
    ): View {
        $payment = $document->payment;
        abort_unless($payment instanceof Payment, 404);
        $payment->loadMissing(['invoice.schoolClass', 'recordedBy']);
        $invoice = $payment->invoice;
        abort_unless($invoice !== null, 404);

        return view('documents.print.fee-receipt', [
            'document' => $document,
            'payment' => $payment,
            'invoice' => $invoice,
            'student' => $student,
            'schoolName' => $schoolName,
            'schoolLetterheadSub' => $schoolLetterheadSub,
            'batch' => collect([$payment]),
            'batchTotal' => (float) $payment->amount,
            'autoPrint' => $autoPrint,
        ]);
    }
}

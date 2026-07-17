<?php

namespace App\Support;

use App\Enums\AcademicTerm;
use App\Enums\DocumentType;
use App\Enums\StudentStatus;
use App\Models\DocumentLog;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentIssuer
{
    /**
     * @param  array{
     *     document_type: DocumentType|string,
     *     student_id: int,
     *     class_id?: int|null,
     *     term?: string|null,
     *     payment_id?: int|null,
     *     reason?: string|null,
     *     date_of_leaving?: string|null,
     *     conduct?: string|null,
     *     academic_progress?: string|null
     * }  $input
     */
    public static function issue(array $input, User $actor): DocumentLog
    {
        $type = $input['document_type'] instanceof DocumentType
            ? $input['document_type']
            : DocumentType::from($input['document_type']);

        $student = Student::query()->with(['primaryGuardian', 'currentEnrollment.schoolClass'])->findOrFail($input['student_id']);
        $force = (bool) ($input['force'] ?? false);

        return DB::transaction(function () use ($type, $student, $input, $actor, $force) {
            [$classId, $term, $paymentId, $meta] = self::resolvePayload($type, $student, $input);

            if (! $force) {
                $existing = self::findReusable($type, $student->id, $classId, $term, $paymentId, $meta);
                if ($existing) {
                    return $existing->loadMissing(['student', 'schoolClass', 'payment', 'generatedBy']);
                }
            }

            $doc = DocumentLog::query()->create([
                'document_number' => DocumentNumbers::nextDocumentNumber(),
                'document_type' => $type,
                'student_id' => $student->id,
                'class_id' => $classId,
                'payment_id' => $paymentId,
                'term' => $term,
                'meta' => $meta,
                'file_url' => null,
                'generated_by' => $actor->id,
                'generated_at' => now(),
            ]);

            $doc->file_url = route('documents.print', $doc, absolute: false);
            $doc->save();

            return $doc->fresh(['student', 'schoolClass', 'payment', 'generatedBy']);
        });
    }

    /**
     * Reuse a recent identical document to absorb double-clicks / Generate+PDF.
     * Fee receipts for the same payment always reuse (same receipt reprint).
     */
    private static function findReusable(
        DocumentType $type,
        int $studentId,
        ?int $classId,
        ?string $term,
        ?int $paymentId,
        ?array $meta,
    ): ?DocumentLog {
        $query = DocumentLog::query()
            ->where('document_type', $type)
            ->where('student_id', $studentId)
            ->when($classId !== null, fn ($q) => $q->where('class_id', $classId), fn ($q) => $q->whereNull('class_id'))
            ->when($term !== null, fn ($q) => $q->where('term', $term), fn ($q) => $q->whereNull('term'))
            ->when($paymentId !== null, fn ($q) => $q->where('payment_id', $paymentId), fn ($q) => $q->whereNull('payment_id'))
            ->latest('id');

        if ($type === DocumentType::FeeReceipt) {
            return $query->first();
        }

        if ($type === DocumentType::TransferCertificate) {
            $query->where('meta->reason', $meta['reason'] ?? null)
                ->where('meta->date_of_leaving', $meta['date_of_leaving'] ?? null)
                ->where('meta->conduct', $meta['conduct'] ?? null)
                ->where('meta->academic_progress', $meta['academic_progress'] ?? null);
        }

        return $query
            ->where('generated_at', '>=', now()->subMinutes(15))
            ->first();
    }

    /**
     * Active enrollment for student in class for the academic year, or null.
     */
    public static function activeEnrollment(Student $student, SchoolClass $schoolClass, string $year): ?Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('class_id', $schoolClass->id)
            ->where('academic_year', $year)
            ->where('status', StudentStatus::Active)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: ?int, 1: ?string, 2: ?int, 3: ?array}
     */
    private static function resolvePayload(DocumentType $type, Student $student, array $input): array
    {
        $year = AcademicYear::current();

        return match ($type) {
            DocumentType::ReportCard => self::reportCardPayload($student, $input, $year),
            DocumentType::CertificateCompletion => self::completionPayload($student, $input, $year),
            DocumentType::TransferCertificate => self::transferPayload($student, $input, $year),
            DocumentType::FeeReceipt => self::feeReceiptPayload($student, $input),
            DocumentType::StudentIdCard => self::idCardPayload($student, $input, $year),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: string, 2: null, 3: array}
     */
    private static function reportCardPayload(Student $student, array $input, string $year): array
    {
        $classId = (int) ($input['class_id'] ?? 0);
        $termRaw = (string) ($input['term'] ?? '');
        if ($classId < 1 || $termRaw === '') {
            throw ValidationException::withMessages([
                'class_id' => 'Class and term are required for a report card.',
            ]);
        }

        $term = AcademicTerm::from($termRaw);
        $schoolClass = SchoolClass::query()->findOrFail($classId);
        if (! self::activeEnrollment($student, $schoolClass, $year)) {
            throw ValidationException::withMessages([
                'student_id' => 'Student is not actively enrolled in the selected class for '.$year.'.',
            ]);
        }

        return [$schoolClass->id, $term->value, null, ['academic_year' => $year]];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: null, 2: null, 3: array}
     */
    private static function completionPayload(Student $student, array $input, string $year): array
    {
        $schoolClass = self::resolveClass($student, $input, $year);
        if ((int) $schoolClass->form_level !== 4) {
            throw ValidationException::withMessages([
                'class_id' => 'Certificate of Completion is for Form 4 students.',
            ]);
        }

        return [$schoolClass->id, null, null, [
            'academic_year' => $year,
            'form_label' => $schoolClass->displayName(),
        ]];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: null, 2: null, 3: array}
     */
    private static function transferPayload(Student $student, array $input, string $year): array
    {
        $schoolClass = self::resolveClass($student, $input, $year);
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Transfer reason is required.',
            ]);
        }

        $leaving = (string) ($input['date_of_leaving'] ?? now()->toDateString());

        return [$schoolClass->id, null, null, [
            'academic_year' => $year,
            'reason' => $reason,
            'date_of_leaving' => $leaving,
            'conduct' => trim((string) ($input['conduct'] ?? 'Good')) ?: 'Good',
            'academic_progress' => trim((string) ($input['academic_progress'] ?? 'Satisfactory')) ?: 'Satisfactory',
            'enrolled_since' => Enrollment::query()
                ->where('student_id', $student->id)
                ->where('class_id', $schoolClass->id)
                ->orderBy('created_at')
                ->value('created_at'),
        ]];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: ?int, 1: null, 2: int, 3: array}
     */
    private static function feeReceiptPayload(Student $student, array $input): array
    {
        $paymentId = (int) ($input['payment_id'] ?? 0);
        $payment = $paymentId > 0
            ? Payment::query()->with('invoice.schoolClass')->where('student_id', $student->id)->find($paymentId)
            : Payment::query()->with('invoice.schoolClass')->where('student_id', $student->id)->latest('paid_at')->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment_id' => 'No payment found for this student to print a fee receipt.',
            ]);
        }

        return [
            $payment->invoice?->class_id,
            null,
            $payment->id,
            ['receipt_number' => $payment->receipt_number],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: null, 2: null, 3: array}
     */
    private static function idCardPayload(Student $student, array $input, string $year): array
    {
        $schoolClass = self::resolveClass($student, $input, $year);

        return [$schoolClass->id, null, null, [
            'academic_year' => $year,
            'valid_through' => $year, // AY label
        ]];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function resolveClass(Student $student, array $input, string $year): SchoolClass
    {
        $classId = (int) ($input['class_id'] ?? 0);
        if ($classId > 0) {
            $schoolClass = SchoolClass::query()->findOrFail($classId);
            if (! self::activeEnrollment($student, $schoolClass, $year)) {
                throw ValidationException::withMessages([
                    'student_id' => 'Student is not actively enrolled in the selected class for '.$year.'.',
                ]);
            }

            return $schoolClass;
        }

        $enrollment = Enrollment::query()
            ->with('schoolClass')
            ->where('student_id', $student->id)
            ->where('academic_year', $year)
            ->where('status', StudentStatus::Active)
            ->latest('id')
            ->first();

        $schoolClass = $enrollment?->schoolClass;
        if (! $schoolClass) {
            throw ValidationException::withMessages([
                'class_id' => 'Select a class for this student.',
            ]);
        }

        return $schoolClass;
    }
}

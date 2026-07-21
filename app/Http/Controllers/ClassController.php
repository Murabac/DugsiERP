<?php

namespace App\Http\Controllers;

use App\Enums\ClassStatus;
use App\Enums\StudentStatus;
use App\Enums\WaitlistStatus;
use App\Models\ClassWaitlist;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Support\AcademicYear;
use App\Support\StudentBulkImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClassController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = AcademicYear::current();
        $user = $request->user();

        $query = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->withCount([
                'activeEnrollments as enrolled_count',
                'waitingList as waitlist_count',
            ])
            ->orderBy('form_level')
            ->orderBy('section');

        if (! $user->isAdmin() && ! $user->hasPermission('classes.manage')
            && ($user->isTeacher() || $user->homeroomClassIds($academicYear) !== [])) {
            $ids = array_values(array_unique(array_merge(
                $user->taughtClassIds($academicYear),
                $user->homeroomClassIds($academicYear),
            )));
            $query->whereIn('id', $ids ?: [0]);
        }

        $classes = $query->get();

        $totalStudents = $classes->sum('enrolled_count');

        return view('classes.index', [
            'classes' => $classes,
            'totalStudents' => $totalStudents,
            'academicYear' => $academicYear,
            'canManage' => $user->isAdmin(),
        ]);
    }

    public function manage(): View
    {
        $classes = SchoolClass::query()
            ->with('homeroomTeacher')
            ->withCount([
                'activeEnrollments as enrolled_count',
                'waitingList as waitlist_count',
            ])
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        $totalStudents = $classes->where('status', ClassStatus::Active)->sum('enrolled_count');
        $totalCapacity = $classes->where('status', ClassStatus::Active)->sum('capacity');

        $currentYear = AcademicYear::current();

        $teachers = Staff::queryEligibleFormMasters()
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code']);

        return view('classes.manage', [
            'classes' => $classes,
            'teachers' => $teachers,
            'totalStudents' => $totalStudents,
            'totalCapacity' => $totalCapacity,
            'academicYear' => $currentYear,
            'academicYears' => array_values(array_unique([$currentYear, '2025-26', '2024-25', '2023-24'])),
            'sectionLetters' => self::sectionLetters(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $letters = self::sectionLetters();

        $data = $request->validate([
            'form_level' => ['required', 'integer', 'between:1,4'],
            'academic_year' => ['required', 'string', 'max:16'],
            'sections' => ['required', 'array', 'min:1', 'max:'.count($letters)],
            'sections.*.section' => ['required', 'string', Rule::in($letters)],
            'sections.*.capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'sections.*.room' => ['required', 'string', 'max:32'],
            'sections.*.homeroom_teacher_id' => ['nullable', 'integer', 'exists:staff,id'],
        ]);

        $formLevel = (int) $data['form_level'];
        $academicYear = $data['academic_year'];
        $normalized = [];
        $seen = [];

        foreach ($data['sections'] as $index => $row) {
            $section = strtoupper(trim((string) $row['section']));
            $room = trim((string) $row['room']);
            $teacherId = $row['homeroom_teacher_id'] ?? null;
            $teacherId = $teacherId !== null && $teacherId !== '' ? (int) $teacherId : null;

            if ($section === '') {
                return back()->withInput()->withErrors([
                    "sections.{$index}.section" => 'Section is required.',
                ]);
            }

            if (isset($seen[$section])) {
                return back()->withInput()->withErrors([
                    "sections.{$index}.section" => "Section {$section} is listed more than once.",
                ]);
            }
            $seen[$section] = true;

            if ($teacherId) {
                $this->assertActiveTeacher($teacherId);
            }

            $exists = SchoolClass::query()
                ->where('form_level', $formLevel)
                ->where('section', $section)
                ->where('academic_year', $academicYear)
                ->exists();

            if ($exists) {
                return back()->withInput()->withErrors([
                    "sections.{$index}.section" => "Form {$formLevel} Section {$section} already exists for {$academicYear}.",
                ]);
            }

            $normalized[] = [
                'form_level' => $formLevel,
                'section' => $section,
                'academic_year' => $academicYear,
                'capacity' => (int) $row['capacity'],
                'room' => $room,
                'homeroom_teacher_id' => $teacherId,
                'status' => ClassStatus::Active,
            ];
        }

        DB::transaction(function () use ($normalized) {
            foreach ($normalized as $row) {
                SchoolClass::query()->create($row);
            }
        });

        $count = count($normalized);
        $message = $count === 1
            ? 'Class created successfully.'
            : "{$count} class sections created successfully.";

        return redirect()
            ->route('classes.manage')
            ->with('status', $message);
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $data = $request->validate([
            'form_level' => ['required', 'integer', 'between:1,4'],
            'section' => ['required', 'string', Rule::in(self::sectionLetters())],
            'academic_year' => ['required', 'string', 'max:16'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'room' => ['required', 'string', 'max:32'],
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:staff,id'],
        ]);

        $data['section'] = strtoupper(trim($data['section']));
        $data['room'] = trim($data['room']);
        $data['homeroom_teacher_id'] = $data['homeroom_teacher_id'] ?? null;

        if ($data['homeroom_teacher_id']) {
            $this->assertActiveTeacher((int) $data['homeroom_teacher_id']);
        }

        $exists = SchoolClass::query()
            ->where('form_level', $data['form_level'])
            ->where('section', $data['section'])
            ->where('academic_year', $data['academic_year'])
            ->where('id', '!=', $schoolClass->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors([
                'section' => 'That Form + Section + Academic Year already exists.',
            ]);
        }

        $hasLinkedStudents = $schoolClass->enrollments()->exists()
            || $schoolClass->waitlistEntries()->exists();

        if ($hasLinkedStudents && $data['academic_year'] !== $schoolClass->academic_year) {
            return back()->withInput()->withErrors([
                'academic_year' => 'Cannot change academic year while students are enrolled or waitlisted. Archive this class and create a new one for the new year.',
            ]);
        }

        $previousCapacity = $schoolClass->capacity;
        $schoolClass->update($data);

        $waiting = $schoolClass->waitingList()->count();
        $openSeats = $schoolClass->fresh()->openSeats();

        $message = 'Class updated successfully.';
        if ((int) $data['capacity'] > $previousCapacity && $waiting > 0 && $openSeats > 0) {
            $message .= " {$waiting} student(s) on the waitlist — open seats available. Enroll them from the class roster.";
        }

        if ($request->input('return_to') === 'roster') {
            return redirect()
                ->route('classes.roster', $schoolClass)
                ->with('status', $message);
        }

        if ((int) $data['capacity'] > $previousCapacity && $waiting > 0 && $openSeats > 0) {
            return redirect()
                ->route('classes.roster', $schoolClass)
                ->with('status', $message);
        }

        return redirect()
            ->route('classes.manage')
            ->with('status', $message);
    }

    public function archive(SchoolClass $schoolClass): RedirectResponse
    {
        $enrolled = $schoolClass->enrolledCount();

        $schoolClass->update(['status' => ClassStatus::Archived]);

        $message = $enrolled > 0
            ? "Class archived. Warning: {$enrolled} student(s) are still enrolled."
            : 'Class archived successfully.';

        return redirect()
            ->route('classes.manage')
            ->with('status', $message);
    }

    public function roster(Request $request, SchoolClass $schoolClass): View
    {
        abort_unless($request->user()->canViewSchoolClass($schoolClass), 403);
        abort_if($schoolClass->status === ClassStatus::Archived && ! $request->user()->isAdmin(), 404);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $enrollments = $schoolClass->enrollments()
            ->with(['student.primaryGuardian'])
            ->whereHas('student', function ($q) use ($search, $status) {
                if ($search !== '') {
                    $q->where(function ($inner) use ($search) {
                        $inner->where('full_name', 'like', "%{$search}%")
                            ->orWhere('student_code', 'like', "%{$search}%");
                    });
                }
                if ($status !== '') {
                    $q->where('status', $status);
                }
            })
            ->orderBy('roll_number')
            ->get();

        $waitlist = $schoolClass->waitingList()
            ->with(['student.primaryGuardian'])
            ->get();

        $schoolClass->load(['homeroomTeacher']);
        $schoolClass->loadCount([
            'activeEnrollments as enrolled_count',
            'waitingList as waitlist_count',
        ]);

        $canAssignFormMaster = $request->user()->hasPermission('classes.manage');

        return view('classes.roster', [
            'schoolClass' => $schoolClass,
            'enrollments' => $enrollments,
            'waitlist' => $waitlist,
            'search' => $search,
            'statusFilter' => $status,
            'canAdd' => $request->user()->hasPermission('students.manage'),
            'canEnrollWaitlist' => $request->user()->hasPermission('classes.manage'),
            'canAssignFormMaster' => $canAssignFormMaster,
            'formMasters' => $canAssignFormMaster
                ? Staff::queryEligibleFormMasters()
                    ->orderBy('full_name')
                    ->get(['id', 'full_name', 'employee_code'])
                : collect(),
        ]);
    }

    public function printRoster(Request $request, SchoolClass $schoolClass): View
    {
        abort_unless($request->user()->canViewSchoolClass($schoolClass), 403);
        abort_if($schoolClass->status === ClassStatus::Archived && ! $request->user()->isAdmin(), 404);

        $enrollments = $schoolClass->enrollments()
            ->with(['student.primaryGuardian'])
            ->whereHas('student', fn ($q) => $q->where('status', StudentStatus::Active))
            ->orderBy('roll_number')
            ->get();

        $schoolClass->load('homeroomTeacher');

        return view('classes.print-roster', [
            'schoolClass' => $schoolClass,
            'enrollments' => $enrollments,
        ]);
    }

    public function downloadStudentBulkTemplate(Request $request, SchoolClass $schoolClass): BinaryFileResponse
    {
        abort_unless($request->user()->canViewSchoolClass($schoolClass), 403);
        abort_if($schoolClass->status === ClassStatus::Archived, 404);

        return StudentBulkImport::downloadTemplate($schoolClass);
    }

    public function bulkUploadStudents(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        abort_unless($request->user()->canViewSchoolClass($schoolClass), 403);
        abort_if($schoolClass->status !== ClassStatus::Active, 404);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt'],
        ]);

        $uploaded = $request->file('file');
        $path = $uploaded->getRealPath();
        if ($path === false) {
            return redirect()
                ->route('classes.roster', $schoolClass)
                ->withErrors(['file' => 'Could not read the uploaded file.']);
        }

        // Format is detected from file contents (not the client filename).
        $tmp = storage_path('app/tmp-bulk-'.uniqid('', true).'.bin');
        if (! is_dir(dirname($tmp))) {
            mkdir(dirname($tmp), 0755, true);
        }
        copy($path, $tmp);

        try {
            $format = StudentBulkImport::detectFormat($tmp);
            $result = StudentBulkImport::import($schoolClass, $tmp, $format);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('classes.roster', $schoolClass)
                ->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('classes.roster', $schoolClass)
                ->withErrors(['file' => 'Could not process the upload. Use the Excel template (.xlsx) or a CSV export.']);
        } finally {
            @unlink($tmp);
        }

        $parts = [];
        if ($result['imported'] > 0) {
            $parts[] = $result['imported'].' enrolled';
        }
        if ($result['waitlisted'] > 0) {
            $parts[] = $result['waitlisted'].' waitlisted (class full)';
        }
        if (($result['duplicates'] ?? 0) > 0) {
            $parts[] = $result['duplicates'].' already in school (skipped)';
        }
        if ($result['skipped'] > 0) {
            $parts[] = $result['skipped'].' invalid (skipped)';
        }

        $status = $parts === []
            ? 'No student rows were imported. Check the template columns and try again.'
            : 'Bulk upload finished: '.implode(', ', $parts).'.';

        $redirect = redirect()->route('classes.roster', $schoolClass)->with('status', $status);

        if ($result['errors'] !== []) {
            $redirect->with('bulk_errors', array_slice($result['errors'], 0, 20));
        }

        return $redirect;
    }

    public function enrollFromWaitlist(SchoolClass $schoolClass, ClassWaitlist $waitlist): RedirectResponse
    {
        abort_unless($waitlist->class_id === $schoolClass->id, 404);
        abort_unless($waitlist->status === WaitlistStatus::Waiting, 404);

        DB::transaction(function () use ($schoolClass, $waitlist) {
            $class = SchoolClass::query()
                ->whereKey($schoolClass->id)
                ->lockForUpdate()
                ->firstOrFail();

            $entry = ClassWaitlist::query()
                ->whereKey($waitlist->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->status !== WaitlistStatus::Waiting) {
                throw ValidationException::withMessages([
                    'waitlist' => 'This waitlist entry is no longer waiting.',
                ]);
            }

            if ($class->isFull()) {
                throw ValidationException::withMessages([
                    'waitlist' => "Class is still full ({$class->capacity} seats). Increase capacity first.",
                ]);
            }

            $student = $entry->student()->lockForUpdate()->firstOrFail();

            $existingEnrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('academic_year', $class->academic_year)
                ->whereIn('status', [StudentStatus::Active, StudentStatus::Suspended])
                ->lockForUpdate()
                ->first();

            if ($existingEnrollment) {
                throw ValidationException::withMessages([
                    'waitlist' => $student->full_name.' is already enrolled in another class for '.$class->academic_year.'.',
                ]);
            }

            Enrollment::query()->create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'academic_year' => $class->academic_year,
                'roll_number' => $class->nextRollNumber(),
                'enrollment_date' => now()->toDateString(),
                'status' => StudentStatus::Active,
            ]);

            $student->update(['status' => StudentStatus::Active]);

            $entry->update([
                'status' => WaitlistStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
        });

        $student = $waitlist->student()->with('primaryGuardian')->first();
        if ($student) {
            try {
                \App\Support\MonthlyInvoiceGenerator::ensureForStudent($student, $schoolClass);
            } catch (\Throwable) {
                // Fee not configured — enrollment still succeeds.
            }
        }

        return redirect()
            ->route('classes.roster', $schoolClass)
            ->with('status', $waitlist->student->full_name.' enrolled from the waitlist.');
    }

    private function assertActiveTeacher(int $staffId): void
    {
        $ok = Staff::queryEligibleFormMasters()
            ->whereKey($staffId)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'homeroom_teacher_id' => 'Select an active teacher or form master as Form Master for this class.',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public static function sectionLetters(): array
    {
        return range('A', 'L');
    }
}

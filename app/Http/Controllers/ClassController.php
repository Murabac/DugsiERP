<?php

namespace App\Http\Controllers;

use App\Enums\ClassStatus;
use App\Enums\StudentStatus;
use App\Enums\WaitlistStatus;
use App\Models\ClassWaitlist;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Support\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = AcademicYear::current();

        $classes = SchoolClass::query()
            ->where('status', ClassStatus::Active)
            ->where('academic_year', $academicYear)
            ->withCount([
                'activeEnrollments as enrolled_count',
                'waitingList as waitlist_count',
            ])
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        $totalStudents = $classes->sum('enrolled_count');

        return view('classes.index', [
            'classes' => $classes,
            'totalStudents' => $totalStudents,
            'academicYear' => $academicYear,
            'canManage' => $request->user()->isAdmin(),
        ]);
    }

    public function manage(): View
    {
        $classes = SchoolClass::query()
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

        return view('classes.manage', [
            'classes' => $classes,
            'totalStudents' => $totalStudents,
            'totalCapacity' => $totalCapacity,
            'academicYear' => $currentYear,
            'academicYears' => array_values(array_unique([$currentYear, '2025-26', '2024-25', '2023-24'])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'form_level' => ['required', 'integer', 'between:1,4'],
            'section' => ['required', 'string', 'max:8'],
            'academic_year' => ['required', 'string', 'max:16'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'room' => ['required', 'string', 'max:32'],
        ]);

        $data['section'] = strtoupper(trim($data['section']));
        $data['status'] = ClassStatus::Active;
        $data['room'] = trim($data['room']);

        $exists = SchoolClass::query()
            ->where('form_level', $data['form_level'])
            ->where('section', $data['section'])
            ->where('academic_year', $data['academic_year'])
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors([
                'section' => 'That Form + Section + Academic Year already exists.',
            ]);
        }

        SchoolClass::query()->create($data);

        return redirect()
            ->route('classes.manage')
            ->with('status', 'Class created successfully.');
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $data = $request->validate([
            'form_level' => ['required', 'integer', 'between:1,4'],
            'section' => ['required', 'string', 'max:8'],
            'academic_year' => ['required', 'string', 'max:16'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $data['section'] = strtoupper(trim($data['section']));

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

        $schoolClass->loadCount([
            'activeEnrollments as enrolled_count',
            'waitingList as waitlist_count',
        ]);

        return view('classes.roster', [
            'schoolClass' => $schoolClass,
            'enrollments' => $enrollments,
            'waitlist' => $waitlist,
            'search' => $search,
            'statusFilter' => $status,
            'canAdd' => $request->user()->isAdmin(),
            'canEnrollWaitlist' => $request->user()->isAdmin(),
        ]);
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

        return redirect()
            ->route('classes.roster', $schoolClass)
            ->with('status', $waitlist->student->full_name.' enrolled from the waitlist.');
    }
}

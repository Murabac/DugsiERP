<?php

namespace App\Http\Controllers;

use App\Enums\ClassStatus;
use App\Enums\StudentStatus;
use App\Enums\TransportAssignmentStatus;
use App\Enums\TransportRouteStatus;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Support\AcademicYear;
use App\Support\MonthlyInvoiceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransportAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $routeId = (int) $request->query('route', 0);
        $classId = (int) $request->query('class', 0);
        $q = trim((string) $request->query('q', ''));

        $routes = TransportRoute::query()
            ->with('vehicle')
            ->withCount('activeAssignments')
            ->where('academic_year', $year)
            ->where('status', TransportRouteStatus::Active)
            ->orderBy('name')
            ->get();

        $assignments = TransportAssignment::query()
            ->with([
                'student.primaryGuardian',
                'student.currentEnrollment.schoolClass',
                'route.vehicle',
            ])
            ->where('academic_year', $year)
            ->where('status', TransportAssignmentStatus::Active)
            ->when($routeId > 0, fn ($query) => $query->where('route_id', $routeId))
            ->when($classId > 0, function ($query) use ($classId, $year) {
                $query->whereHas('student.enrollments', fn ($e) => $e
                    ->where('class_id', $classId)
                    ->where('academic_year', $year)
                    ->where('status', StudentStatus::Active));
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('student', fn ($s) => $s
                    ->where('full_name', 'like', '%'.$q.'%')
                    ->orWhere('student_code', 'like', '%'.$q.'%'));
            })
            ->latest('id')
            ->get();

        $classes = SchoolClass::query()
            ->where('academic_year', $year)
            ->where('status', ClassStatus::Active)
            ->orderBy('form_level')
            ->orderBy('section')
            ->get();

        $unassignedStudents = Student::query()
            ->with(['currentEnrollment.schoolClass'])
            ->where('status', StudentStatus::Active)
            ->whereHas('enrollments', fn ($e) => $e
                ->where('academic_year', $year)
                ->where('status', StudentStatus::Active)
                ->when($classId > 0, fn ($e) => $e->where('class_id', $classId)))
            ->whereDoesntHave('transportAssignments', fn ($a) => $a
                ->where('academic_year', $year)
                ->where('status', TransportAssignmentStatus::Active))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($s) use ($q) {
                    $s->where('full_name', 'like', '%'.$q.'%')
                        ->orWhere('student_code', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'full_name', 'student_code']);

        $selectedRoute = $routeId > 0 ? $routes->firstWhere('id', $routeId) : null;

        return view('transport.assignments', [
            'assignments' => $assignments,
            'routes' => $routes,
            'classes' => $classes,
            'unassignedStudents' => $unassignedStudents,
            'academicYear' => $year,
            'routeId' => $routeId,
            'classId' => $classId,
            'q' => $q,
            'selectedRoute' => $selectedRoute,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $year = AcademicYear::current();

        $data = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id', 'required_without:student_ids'],
            'student_ids' => ['nullable', 'array', 'min:1', 'required_without:student_id'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'route_id' => ['required', 'integer', 'exists:transport_routes,id'],
            'started_on' => ['nullable', 'date'],
        ]);

        $studentIds = collect($data['student_ids'] ?? [])
            ->when(isset($data['student_id']), fn ($c) => $c->push($data['student_id']))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            throw ValidationException::withMessages([
                'student_ids' => 'Select at least one student.',
            ]);
        }

        $route = TransportRoute::query()->with('vehicle')->findOrFail($data['route_id']);
        $startedOn = $data['started_on'] ?? now()->toDateString();
        $count = $studentIds->count();

        DB::transaction(function () use ($studentIds, $route, $year, $startedOn) {
            $students = Student::query()
                ->whereIn('id', $studentIds->all())
                ->get()
                ->keyBy('id');

            $used = TransportAssignment::query()
                ->where('route_id', $route->id)
                ->where('status', TransportAssignmentStatus::Active)
                ->lockForUpdate()
                ->count();

            $needed = $studentIds->count();
            if ($used + $needed > $route->capacity()) {
                $free = max(0, $route->capacity() - $used);
                throw ValidationException::withMessages([
                    'route_id' => "Not enough seats. Bus has {$free} free; you selected {$needed}.",
                    'student_ids' => "Not enough seats. Bus has {$free} free; you selected {$needed}.",
                ]);
            }

            foreach ($studentIds as $id) {
                $student = $students->get($id);
                if (! $student) {
                    throw ValidationException::withMessages([
                        'student_ids' => 'One or more selected students were not found.',
                    ]);
                }

                $this->assertAssignable($student, $route, $year, ignoreCapacity: true);

                TransportAssignment::query()->create([
                    'student_id' => $student->id,
                    'route_id' => $route->id,
                    'stop_id' => null,
                    'academic_year' => $year,
                    'status' => TransportAssignmentStatus::Active,
                    'started_on' => $startedOn,
                    'ended_on' => null,
                ]);

                MonthlyInvoiceGenerator::recalculateUnpaid($student, $year);
            }
        });

        $redirect = $request->input('redirect_to');
        if (is_string($redirect) && str_starts_with($redirect, url('/'))) {
            return redirect($redirect)->with('status', $count === 1
                ? 'Student assigned to transport.'
                : "{$count} students assigned to transport.");
        }

        return redirect()
            ->route('transport.assignments.index', [
                'route' => $route->id,
                'class' => $request->input('class'),
            ])
            ->with('status', $count === 1
                ? 'Student assigned to transport.'
                : "{$count} students assigned to transport.");
    }

    public function update(Request $request, TransportAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->isActive(), 404);

        $year = AcademicYear::current();
        $data = $request->validate([
            'route_id' => ['required', 'integer', 'exists:transport_routes,id'],
        ]);

        $route = TransportRoute::query()->with('vehicle')->findOrFail($data['route_id']);
        $student = $assignment->student;

        $this->assertAssignable($student, $route, $year, $assignment);

        DB::transaction(function () use ($assignment, $route, $student, $year) {
            $assignment->update([
                'route_id' => $route->id,
                'stop_id' => null,
            ]);
            MonthlyInvoiceGenerator::recalculateUnpaid($student, $year);
        });

        return redirect()
            ->back()
            ->with('status', 'Transport assignment updated.');
    }

    public function end(Request $request, TransportAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->isActive(), 404);

        $student = $assignment->student;
        $year = $assignment->academic_year;

        DB::transaction(function () use ($assignment, $student, $year) {
            $assignment->update([
                'status' => TransportAssignmentStatus::Ended,
                'ended_on' => now()->toDateString(),
            ]);
            MonthlyInvoiceGenerator::recalculateUnpaid($student, $year);
        });

        return redirect()
            ->back()
            ->with('status', 'Transport assignment ended. Unpaid invoices recalculated.');
    }

    private function assertAssignable(
        Student $student,
        TransportRoute $route,
        string $year,
        ?TransportAssignment $ignore = null,
        bool $ignoreCapacity = false,
    ): void {
        if ($route->academic_year !== $year || $route->status !== TransportRouteStatus::Active) {
            throw ValidationException::withMessages([
                'route_id' => 'Choose an active bus for the current academic year.',
            ]);
        }

        $enrolled = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year', $year)
            ->where('status', StudentStatus::Active)
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'student_ids' => $student->full_name.' must have an active enrollment this year.',
            ]);
        }

        $existing = TransportAssignment::query()
            ->where('student_id', $student->id)
            ->where('academic_year', $year)
            ->where('status', TransportAssignmentStatus::Active)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'student_ids' => $student->full_name.' already has an active transport assignment.',
            ]);
        }

        if ($ignoreCapacity) {
            return;
        }

        $used = TransportAssignment::query()
            ->where('route_id', $route->id)
            ->where('status', TransportAssignmentStatus::Active)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->count();

        if ($used >= $route->capacity()) {
            throw ValidationException::withMessages([
                'route_id' => 'Bus is at capacity ('.$route->capacity().' seats).',
            ]);
        }
    }
}

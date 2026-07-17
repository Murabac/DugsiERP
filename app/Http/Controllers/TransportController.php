<?php

namespace App\Http\Controllers;

use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\TransportRouteStatus;
use App\Enums\VehicleStatus;
use App\Models\Invoice;
use App\Models\SchoolSetting;
use App\Models\Staff;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use App\Support\AcademicYear;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransportController extends Controller
{
    public function index(): View
    {
        $year = AcademicYear::current();

        $buses = TransportRoute::query()
            ->with(['vehicle.driver'])
            ->withCount(['activeAssignments'])
            ->where('academic_year', $year)
            ->orderBy('name')
            ->get();

        $active = $buses->where('status', TransportRouteStatus::Active);
        $seatsTotal = $active->sum(fn (TransportRoute $r) => $r->capacity());
        $seatsUsed = (int) $active->sum('active_assignments_count');

        $monthStart = now()->startOfMonth()->toDateString();
        $transportBilled = Money::round(
            (float) Invoice::query()
                ->whereDate('billing_month', $monthStart)
                ->sum('transport_fee')
        );

        return view('transport.index', [
            'academicYear' => $year,
            'buses' => $buses,
            'busCount' => $active->count(),
            'riders' => $seatsUsed,
            'seatsTotal' => $seatsTotal,
            'seatsFree' => max(0, $seatsTotal - $seatsUsed),
            'transportFeeUsd' => SchoolSetting::transportFeeUsd(),
            'transportBilled' => $transportBilled,
            'billingMonth' => now()->format('F Y'),
        ]);
    }

    public function create(): View
    {
        return view('transport.register', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        [$vehicleData, $routeData] = $this->validatedBus($request);

        $route = DB::transaction(function () use ($vehicleData, $routeData) {
            $vehicle = Vehicle::query()->create($vehicleData);

            return TransportRoute::query()->create([
                ...$routeData,
                'vehicle_id' => $vehicle->id,
            ]);
        });

        return redirect()
            ->route('transport.buses.show', $route)
            ->with('status', 'Bus registered.');
    }

    public function show(TransportRoute $route): View
    {
        $route->load([
            'vehicle.driver',
            'activeAssignments.student.primaryGuardian',
            'activeAssignments.student.currentEnrollment.schoolClass',
        ]);

        $assignments = $route->activeAssignments
            ->sortBy(fn ($a) => $a->student?->full_name ?? '')
            ->values();

        return view('transport.show', [
            'route' => $route,
            'assignments' => $assignments,
        ]);
    }

    public function edit(TransportRoute $route): View
    {
        $route->load('vehicle');

        return view('transport.register', [
            ...$this->formData($route),
            'route' => $route,
        ]);
    }

    public function update(Request $request, TransportRoute $route): RedirectResponse
    {
        $route->load('vehicle');
        [$vehicleData, $routeData] = $this->validatedBus($request, $route);

        DB::transaction(function () use ($route, $vehicleData, $routeData) {
            $route->vehicle?->update($vehicleData);
            $route->update($routeData);
        });

        return redirect()
            ->route('transport.buses.show', $route)
            ->with('status', 'Bus updated.');
    }

    public function print(TransportRoute $route): View
    {
        $route->load([
            'vehicle.driver',
            'activeAssignments.student.primaryGuardian',
            'activeAssignments.student.currentEnrollment.schoolClass',
        ]);

        $riders = $route->activeAssignments
            ->sortBy(fn ($a) => $a->student?->full_name ?? '')
            ->values();

        return view('transport.print', [
            'route' => $route,
            'riders' => $riders,
            'schoolName' => SchoolSetting::schoolName(),
            'schoolLetterheadSub' => SchoolSetting::schoolLetterheadSub(),
        ]);
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'return_to' => ['nullable', 'string', 'max:500'],
            // Draft bus fields — kept so the register form is restored after reload
            'route_name' => ['nullable', 'string', 'max:120'],
            'plate_number' => ['nullable', 'string', 'max:32'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $staff = Staff::query()->create([
            'employee_code' => Staff::nextEmployeeCode(),
            'full_name' => trim($data['full_name']),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'role_label' => StaffRoleLabel::Driver,
            'status' => StaffStatus::Active,
            'date_joined' => now()->toDateString(),
        ]);

        $returnTo = $data['return_to'] ?? route('transport.buses.create');
        if (! is_string($returnTo) || ! str_starts_with($returnTo, url('/'))) {
            $returnTo = route('transport.buses.create');
        }

        $draft = array_filter([
            'route_name' => $data['route_name'] ?? null,
            'plate_number' => $data['plate_number'] ?? null,
            'capacity' => isset($data['capacity']) ? (string) $data['capacity'] : null,
            'status' => $data['status'] ?? null,
            'driver_staff_id' => (string) $staff->id,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect($returnTo)
            ->withInput($draft)
            ->with('status', 'Driver added.')
            ->with('selected_driver_id', $staff->id);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function validatedBus(Request $request, ?TransportRoute $route = null): array
    {
        $year = AcademicYear::current();

        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:120'],
            'plate_number' => [
                'required',
                'string',
                'max:32',
                Rule::unique('vehicles', 'plate_number')->ignore($route?->vehicle_id),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'driver_staff_id' => [
                'nullable',
                'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('role_label', StaffRoleLabel::Driver->value)),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $active = $data['status'] === 'active';

        $vehicleData = [
            'plate_number' => trim($data['plate_number']),
            'label' => trim($data['route_name']),
            'capacity' => (int) $data['capacity'],
            'make_model' => null,
            'status' => $active ? VehicleStatus::Active : VehicleStatus::Retired,
            'driver_staff_id' => $data['driver_staff_id'] ?? null,
        ];

        $routeData = [
            'name' => trim($data['route_name']),
            'code' => null,
            'academic_year' => $route?->academic_year ?? $year,
            'status' => $active ? TransportRouteStatus::Active : TransportRouteStatus::Inactive,
            'notes' => null,
        ];

        return [$vehicleData, $routeData];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?TransportRoute $route = null): array
    {
        return [
            'route' => $route,
            'drivers' => Staff::query()
                ->where('status', StaffStatus::Active)
                ->where('role_label', StaffRoleLabel::Driver)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_code', 'phone']),
            'academicYear' => AcademicYear::current(),
            'selectedDriverId' => session('selected_driver_id'),
        ];
    }
}

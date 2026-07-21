<?php

namespace Tests\Feature;

use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\TransportAssignmentStatus;
use App\Enums\TransportRouteStatus;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AcademicYear;
use App\Support\FeeCalculator;
use App\Support\MonthlyInvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SchoolSetting::set('monthly_fee_usd', '45');
        SchoolSetting::set('transport_fee_usd', '15');
        SchoolSetting::set('sibling_discount_percent', '10');
        SchoolSetting::set('need_based_discount_percent', '20');
        FeeCalculator::clearSiblingCache();
    }

    /**
     * @return array{admin: User, finance: User, teacher: User, class: SchoolClass, student: Student, vehicle: Vehicle, route: TransportRoute, driver: Staff}
     */
    private function seedTransportBasics(int $capacity = 2): array
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $year = AcademicYear::current();

        $driver = Staff::query()->create([
            'employee_code' => 'EMP-DRV',
            'full_name' => 'Demo Driver',
            'role_label' => StaffRoleLabel::Driver,
            'status' => StaffStatus::Active,
            'date_joined' => now()->toDateString(),
        ]);

        $class = SchoolClass::query()->create([
            'form_level' => 1,
            'section' => 'A',
            'academic_year' => $year,
            'capacity' => 30,
            'room' => 'R-1A',
            'status' => ClassStatus::Active,
        ]);

        $student = Student::query()->create([
            'student_code' => 'STU-T01',
            'full_name' => 'Transport Rider',
            'dob' => '2010-01-01',
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

        $vehicle = Vehicle::query()->create([
            'plate_number' => 'SL-1001',
            'label' => 'North Loop',
            'capacity' => $capacity,
            'status' => VehicleStatus::Active,
            'driver_staff_id' => $driver->id,
        ]);

        $route = TransportRoute::query()->create([
            'name' => 'North Loop',
            'code' => null,
            'vehicle_id' => $vehicle->id,
            'academic_year' => $year,
            'status' => TransportRouteStatus::Active,
        ]);

        return compact('admin', 'finance', 'teacher', 'class', 'student', 'vehicle', 'route', 'driver');
    }

    public function test_admin_and_finance_can_open_transport_teacher_cannot(): void
    {
        ['admin' => $admin, 'finance' => $finance, 'teacher' => $teacher] = $this->seedTransportBasics();

        $this->actingAs($admin)->get(route('transport.index'))->assertOk()->assertSee('Transport');
        $this->actingAs($finance)->get(route('transport.index'))->assertOk();
        $this->actingAs($teacher)->get(route('transport.index'))->assertForbidden();
    }

    public function test_assign_at_capacity_fails_under_capacity_succeeds(): void
    {
        ['admin' => $admin, 'class' => $class, 'route' => $route] = $this->seedTransportBasics(1);
        $year = AcademicYear::current();

        $first = Student::query()->create([
            'student_code' => 'STU-T02',
            'full_name' => 'First Seat',
            'dob' => '2010-02-01',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $first->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 2,
        ]);

        $second = Student::query()->create([
            'student_code' => 'STU-T03',
            'full_name' => 'Overflow',
            'dob' => '2010-03-01',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);
        Enrollment::query()->create([
            'student_id' => $second->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 3,
        ]);

        $this->actingAs($admin)
            ->post(route('transport.assignments.store'), [
                'student_id' => $first->id,
                'route_id' => $route->id,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->from(route('transport.assignments.index'))
            ->post(route('transport.assignments.store'), [
                'student_id' => $second->id,
                'route_id' => $route->id,
            ])
            ->assertSessionHasErrors('route_id');
    }

    public function test_assignment_adds_transport_fee_to_unpaid_invoice(): void
    {
        ['admin' => $admin, 'student' => $student, 'class' => $class, 'route' => $route] = $this->seedTransportBasics();
        $year = AcademicYear::current();

        MonthlyInvoiceGenerator::ensureForStudent($student, $class, now()->startOfMonth(), $year);

        $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(0.0, (float) $invoice->transport_fee);
        $this->assertSame(45.0, (float) $invoice->amount_due);

        $this->actingAs($admin)
            ->post(route('transport.assignments.store'), [
                'student_id' => $student->id,
                'route_id' => $route->id,
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(15.0, (float) $invoice->transport_fee);
        $this->assertSame(60.0, (float) $invoice->amount_due);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);

        $assignment = TransportAssignment::query()->where('student_id', $student->id)->firstOrFail();
        $this->actingAs($admin)
            ->post(route('transport.assignments.end', $assignment))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->transport_fee);
        $this->assertSame(45.0, (float) $invoice->amount_due);
        $this->assertSame(TransportAssignmentStatus::Ended, $assignment->fresh()->status);
    }

    public function test_print_roster_ok(): void
    {
        ['admin' => $admin, 'student' => $student, 'route' => $route] = $this->seedTransportBasics();

        TransportAssignment::query()->create([
            'student_id' => $student->id,
            'route_id' => $route->id,
            'stop_id' => null,
            'academic_year' => AcademicYear::current(),
            'status' => TransportAssignmentStatus::Active,
            'started_on' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('transport.buses.print', $route))
            ->assertOk()
            ->assertSee('Bus roster')
            ->assertSee('Transport Rider');
    }

    public function test_bus_register_uses_driver_only_and_add_driver(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        Staff::query()->create([
            'employee_code' => 'EMP-TCH',
            'full_name' => 'Not A Driver',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
            'date_joined' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('transport.buses.create'))
            ->assertOk()
            ->assertDontSee('Not A Driver')
            ->assertSee('Add driver');

        $this->actingAs($admin)
            ->post(route('transport.drivers.store'), [
                'full_name' => 'New Bus Driver',
                'phone' => '+252634001111',
                'return_to' => route('transport.buses.create'),
                'route_name' => 'South Loop',
                'plate_number' => 'SL-NEW-99',
                'capacity' => 25,
                'status' => 'active',
            ])
            ->assertRedirect(route('transport.buses.create'))
            ->assertSessionHasInput('route_name', 'South Loop')
            ->assertSessionHasInput('plate_number', 'SL-NEW-99')
            ->assertSessionHasInput('capacity', '25');

        $driver = Staff::query()->where('full_name', 'New Bus Driver')->firstOrFail();
        $this->assertSame(StaffRoleLabel::Driver->value, $driver->role_label);

        $this->actingAs($admin)
            ->get(route('transport.buses.create'))
            ->assertOk()
            ->assertSee('South Loop')
            ->assertSee('SL-NEW-99')
            ->assertSee('New Bus Driver');

        $this->actingAs($admin)
            ->post(route('transport.buses.store'), [
                'route_name' => 'South Loop',
                'plate_number' => 'SL-NEW-99',
                'capacity' => 25,
                'driver_staff_id' => $driver->id,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'SL-NEW-99',
            'driver_staff_id' => $driver->id,
        ]);
        $this->assertDatabaseHas('transport_routes', [
            'name' => 'South Loop',
            'status' => 'active',
        ]);
    }

    public function test_bulk_assign_multiple_students_to_bus(): void
    {
        ['admin' => $admin, 'class' => $class, 'route' => $route] = $this->seedTransportBasics(5);
        $year = AcademicYear::current();

        $students = collect([
            ['code' => 'STU-B01', 'name' => 'Bulk One'],
            ['code' => 'STU-B02', 'name' => 'Bulk Two'],
            ['code' => 'STU-B03', 'name' => 'Bulk Three'],
        ])->map(function (array $row, int $i) use ($class, $year) {
            $student = Student::query()->create([
                'student_code' => $row['code'],
                'full_name' => $row['name'],
                'dob' => '2010-01-01',
                'gender' => Gender::Male,
                'status' => StudentStatus::Active,
            ]);
            Enrollment::query()->create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'academic_year' => $year,
                'enrollment_date' => now()->toDateString(),
                'status' => StudentStatus::Active,
                'roll_number' => 10 + $i,
            ]);

            return $student;
        });

        $this->actingAs($admin)
            ->post(route('transport.assignments.store'), [
                'route_id' => $route->id,
                'student_ids' => $students->pluck('id')->all(),
            ])
            ->assertRedirect(route('transport.assignments.index', ['route' => $route->id]))
            ->assertSessionHas('status');

        foreach ($students as $student) {
            $this->assertDatabaseHas('transport_assignments', [
                'student_id' => $student->id,
                'route_id' => $route->id,
                'status' => TransportAssignmentStatus::Active->value,
            ]);
        }
    }

    public function test_bulk_assign_rejects_when_over_capacity(): void
    {
        ['admin' => $admin, 'class' => $class, 'route' => $route] = $this->seedTransportBasics(1);
        $year = AcademicYear::current();

        $ids = [];
        foreach ([['STU-O1', 'Over One'], ['STU-O2', 'Over Two']] as $i => [$code, $name]) {
            $student = Student::query()->create([
                'student_code' => $code,
                'full_name' => $name,
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
                'roll_number' => 20 + $i,
            ]);
            $ids[] = $student->id;
        }

        $this->actingAs($admin)
            ->from(route('transport.assignments.index', ['route' => $route->id]))
            ->post(route('transport.assignments.store'), [
                'route_id' => $route->id,
                'student_ids' => $ids,
            ])
            ->assertSessionHasErrors(['route_id', 'student_ids']);
    }

    public function test_finance_can_view_student_transport_tab(): void
    {
        ['finance' => $finance, 'student' => $student] = $this->seedTransportBasics();

        $this->actingAs($finance)
            ->get(route('students.show', ['student' => $student, 'tab' => 'transport']))
            ->assertOk()
            ->assertViewHas('canSeeTransport', true)
            ->assertDontSee('Need-based fee discount');
    }
}

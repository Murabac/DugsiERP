<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\ClassStatus;
use App\Enums\Gender;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\SchoolWeek;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{admin: User, class: SchoolClass, student: Student, enrollment: Enrollment}
     */
    private function seedClassWithStudent(): array
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
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
            'student_code' => 'STU-501',
            'full_name' => 'Faadumo Xasan Warsame',
            'dob' => '2010-03-15',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
        Guardian::query()->create([
            'student_id' => $student->id,
            'full_name' => 'Xasan Warsame Jama',
            'phone' => '+252634001234',
            'relationship' => 'father',
            'is_primary' => true,
        ]);
        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year' => $year,
            'enrollment_date' => now()->toDateString(),
            'status' => StudentStatus::Active,
            'roll_number' => 1,
        ]);

        return compact('admin', 'class', 'student', 'enrollment');
    }

    public function test_admin_can_mark_attendance_and_send_absence_sms(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedClassWithStudent();
        $date = '2026-07-15'; // Wednesday

        $this->actingAs($admin)
            ->get(route('attendance.index', ['class' => $class->id, 'date' => $date]))
            ->assertOk()
            ->assertSee('Mark Attendance')
            ->assertSee('Faadumo Xasan Warsame')
            ->assertDontSee('value="present" checked', false);

        $this->actingAs($admin)
            ->post(route('attendance.store'), [
                'class_id' => $class->id,
                'date' => $date,
                'statuses' => [$student->id => AttendanceStatus::Absent->value],
                'reasons' => [$student->id => 'Sick'],
                'send_sms' => '1',
            ])
            ->assertRedirect(route('attendance.index', ['class' => $class->id, 'date' => $date]));

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'status' => AttendanceStatus::Absent->value,
            'reason' => 'Sick',
            'marked_by' => $admin->id,
        ]);
        $this->assertTrue(
            AttendanceRecord::query()
                ->where('student_id', $student->id)
                ->whereDate('date', $date)
                ->exists()
        );

        $this->assertDatabaseHas('notifications_log', [
            'type' => NotificationType::AbsenceAlert->value,
            'related_student_id' => $student->id,
            'recipient_phone' => '+252634001234',
            'status' => NotificationStatus::Failed->value,
        ]);

        $log = NotificationLog::query()->first();
        $this->assertStringContainsString('Faadumo Xasan Warsame', $log->message_body);
        $this->assertStringContainsString('credentials', strtolower((string) $log->error));
    }

    public function test_absence_sms_uses_absence_alert_notification_template(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedClassWithStudent();
        $date = '2026-07-15';

        NotificationTemplate::query()->updateOrCreate(
            ['type' => NotificationType::AbsenceAlert->value],
            [
                'name' => 'Absence Alert',
                'channel' => 'sms',
                'body' => 'TEMPLATE-ABSENCE {student_name} / {class} / {date}',
                'variables' => ['student_name', 'class', 'date'],
                'is_active' => true,
            ]
        );

        $this->actingAs($admin)
            ->get(route('attendance.index', ['class' => $class->id, 'date' => $date]))
            ->assertOk()
            ->assertSee('TEMPLATE-ABSENCE', false)
            ->assertSee('Absence Alert template');

        $this->actingAs($admin)
            ->post(route('attendance.store'), [
                'class_id' => $class->id,
                'date' => $date,
                'statuses' => [$student->id => AttendanceStatus::Absent->value],
                'reasons' => [$student->id => 'Sick'],
                'send_sms' => '1',
            ])
            ->assertRedirect();

        $log = NotificationLog::query()
            ->where('type', NotificationType::AbsenceAlert)
            ->where('related_student_id', $student->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(
            'TEMPLATE-ABSENCE Faadumo Xasan Warsame / '.$class->displayName().' / 15 July 2026',
            $log->message_body
        );
        $this->assertStringNotContainsString('was absent from school', $log->message_body);
    }

    public function test_inactive_absence_alert_template_skips_sms(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedClassWithStudent();
        $date = '2026-07-15';

        NotificationTemplate::query()->updateOrCreate(
            ['type' => NotificationType::AbsenceAlert->value],
            [
                'name' => 'Absence Alert',
                'channel' => 'sms',
                'body' => 'INACTIVE {student_name}',
                'variables' => ['student_name', 'class', 'date'],
                'is_active' => false,
            ]
        );

        $this->actingAs($admin)
            ->post(route('attendance.store'), [
                'class_id' => $class->id,
                'date' => $date,
                'statuses' => [$student->id => AttendanceStatus::Absent->value],
                'reasons' => [$student->id => 'Sick'],
                'send_sms' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'inactive'));

        $this->assertSame(0, NotificationLog::query()
            ->where('type', NotificationType::AbsenceAlert)
            ->where('related_student_id', $student->id)
            ->count());
    }

    public function test_attendance_history_and_print_views(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedClassWithStudent();
        $date = '2026-07-15';

        AttendanceRecord::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'date' => $date,
            'status' => AttendanceStatus::Present,
            'marked_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('attendance.history', [
                'class' => $class->id,
                'from' => '2026-07-01',
                'to' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertSee('Attendance History')
            ->assertSee('Wed, 15 Jul 2026');

        $this->actingAs($admin)
            ->get(route('attendance.print', ['class' => $class->id, 'date' => $date]))
            ->assertOk()
            ->assertSee('Attendance Register')
            ->assertSee('Faadumo Xasan Warsame')
            ->assertSee('Present');
    }

    public function test_week_sheet_shows_empty_sat_to_wed_columns(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedClassWithStudent();

        $this->actingAs($admin)
            ->get(route('attendance.week-sheet', [
                'class' => $class->id,
                'week' => '2026-07-15', // Wednesday → week Sat 11 Jul – Wed 15 Jul
            ]))
            ->assertOk()
            ->assertSee('Week Sheet')
            ->assertSee('Saturday')
            ->assertSee('Sunday')
            ->assertSee('Monday')
            ->assertSee('Tuesday')
            ->assertSee('Wednesday')
            ->assertSee($student->full_name)
            ->assertSee('11 Jul')
            ->assertSee('15 Jul')
            ->assertSee('Empty (blank boxes)');

        AttendanceRecord::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'date' => '2026-07-13', // Monday
            'status' => AttendanceStatus::Present,
            'marked_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('attendance.week-sheet', [
                'class' => $class->id,
                'week' => '2026-07-11',
                'fill' => 'marked',
            ]))
            ->assertOk()
            ->assertSee('Fill marked days')
            ->assertSee('✓');

        $this->actingAs($admin)
            ->get(route('attendance.week-sheet', [
                'class' => $class->id,
                'week' => '2026-07-11',
                'fill' => 'marked',
                'print' => 1,
            ]))
            ->assertOk()
            ->assertSee('Weekly Attendance Sheet')
            ->assertSee('With marked days')
            ->assertSee($student->full_name)
            ->assertSee('✓')
            ->assertSee('◐ = Late')
            ->assertSee('✗ = Absent')
            ->assertSee('⊘ = Suspended');
    }

    public function test_teacher_cannot_mark_untought_class(): void
    {
        ['class' => $class] = $this->seedClassWithStudent();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-ATT',
            'full_name' => 'Other Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create(['staff_id' => $staff->id]);

        $this->actingAs($teacher)
            ->get(route('attendance.index', ['class' => $class->id]))
            ->assertForbidden();
    }

    public function test_teacher_can_mark_taught_class(): void
    {
        ['class' => $class, 'student' => $student] = $this->seedClassWithStudent();
        $year = AcademicYear::current();
        $staff = Staff::query()->create([
            'employee_code' => 'EMP-ATT2',
            'full_name' => 'Class Teacher',
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
        ]);
        $teacher = User::factory()->role(UserRole::Teacher)->create(['staff_id' => $staff->id]);
        $subject = Subject::query()->create(['name' => 'Mathematics', 'sort_order' => 1]);
        $period = SchoolWeek::period(1);
        TimetableSlot::query()->create([
            'class_id' => $class->id,
            'academic_year' => $year,
            'day_of_week' => 'wed',
            'period_number' => 1,
            'start_time' => $period['start'],
            'end_time' => $period['end'],
            'subject_id' => $subject->id,
            'teacher_id' => $staff->id,
            'room' => 'R-1A',
        ]);

        $this->actingAs($teacher)
            ->get(route('attendance.index', ['class' => $class->id, 'date' => '2026-07-15']))
            ->assertOk()
            ->assertSee('Faadumo Xasan Warsame');

        $this->actingAs($teacher)
            ->post(route('attendance.store'), [
                'class_id' => $class->id,
                'date' => '2026-07-15',
                'statuses' => [$student->id => AttendanceStatus::Late->value],
                'send_sms' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'status' => AttendanceStatus::Late->value,
            'marked_by' => $teacher->id,
        ]);
    }

    public function test_student_profile_shows_attendance_tab(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedClassWithStudent();

        AttendanceRecord::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'date' => '2026-07-14',
            'status' => AttendanceStatus::Present,
            'marked_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('students.show', ['student' => $student, 'tab' => 'attendance']))
            ->assertOk()
            ->assertSee('Attendance History')
            ->assertSee('Present');
    }

    public function test_invalid_date_query_falls_back_gracefully(): void
    {
        ['admin' => $admin, 'class' => $class] = $this->seedClassWithStudent();

        $this->actingAs($admin)
            ->get(route('attendance.index', ['class' => $class->id, 'date' => 'not-a-date']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('attendance.history', [
                'class' => $class->id,
                'from' => 'garbage',
                'to' => 'also-bad',
            ]))
            ->assertOk();
    }

    public function test_resaving_absent_does_not_duplicate_sms_stub(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedClassWithStudent();
        $date = '2026-07-15';
        $payload = [
            'class_id' => $class->id,
            'date' => $date,
            'statuses' => [$student->id => AttendanceStatus::Absent->value],
            'reasons' => [$student->id => 'Sick'],
            'send_sms' => '1',
        ];

        $this->actingAs($admin)->post(route('attendance.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('attendance.store'), $payload)->assertRedirect();

        $this->assertSame(1, NotificationLog::query()
            ->where('related_student_id', $student->id)
            ->where('type', NotificationType::AbsenceAlert)
            ->count());
    }

    public function test_can_save_suspended_attendance(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedClassWithStudent();
        $date = '2026-07-15';

        $this->actingAs($admin)
            ->post(route('attendance.store'), [
                'class_id' => $class->id,
                'date' => $date,
                'statuses' => [$student->id => AttendanceStatus::Suspended->value],
                'reasons' => [$student->id => 'Disciplinary'],
                'send_sms' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'status' => AttendanceStatus::Suspended->value,
            'reason' => 'Disciplinary',
        ]);

        $this->assertDatabaseMissing('notifications_log', [
            'related_student_id' => $student->id,
        ]);
    }

    public function test_future_dates_cannot_be_marked(): void
    {
        ['admin' => $admin, 'class' => $class, 'student' => $student] = $this->seedClassWithStudent();
        $this->travelTo(Carbon::parse('2026-07-17 10:00:00'));

        $future = '2026-07-20';

        $this->actingAs($admin)
            ->get(route('attendance.index', ['class' => $class->id, 'date' => $future]))
            ->assertOk()
            ->assertSee('Future dates cannot be marked')
            ->assertDontSee('Save Attendance');

        $this->actingAs($admin)
            ->from(route('attendance.index', ['class' => $class->id, 'date' => $future]))
            ->post(route('attendance.store'), [
                'class_id' => $class->id,
                'date' => $future,
                'statuses' => [$student->id => AttendanceStatus::Present->value],
                'send_sms' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('date');

        $this->assertDatabaseMissing('attendance_records', [
            'student_id' => $student->id,
            'date' => $future,
        ]);
    }
}

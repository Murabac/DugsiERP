<?php

namespace Tests\Feature;

use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use App\Enums\UserRole;
use App\Models\SchoolSetting;
use App\Models\Staff;
use App\Models\StaffAttendanceRecord;
use App\Models\StaffWebauthnCredential;
use App\Models\User;
use App\Support\StaffAttendancePunch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(string $name = 'Amina Teacher'): Staff
    {
        return Staff::query()->create([
            'employee_code' => Staff::nextEmployeeCode(),
            'full_name' => $name,
            'role_label' => StaffRoleLabel::Teacher,
            'status' => StaffStatus::Active,
            'subject_specialty' => 'English',
            'date_joined' => now()->toDateString(),
        ]);
    }

    public function test_admin_views_staff_history_and_can_mark_day_teacher_and_finance_cannot(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();
        $staff = $this->makeStaff();

        $this->actingAs($teacher)->get(route('staff-attendance.history'))->assertForbidden();
        $this->actingAs($finance)->get(route('staff-attendance.history'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('staff-attendance.history'))
            ->assertOk()
            ->assertSee('Staff attendance history');

        $this->actingAs($admin)
            ->get(route('staff-attendance.index'))
            ->assertOk()
            ->assertSee($staff->full_name)
            ->assertSee('Save attendance')
            ->assertDontSee('read-only');
    }

    public function test_admin_can_manually_mark_staff_filtered_by_role(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = $this->makeStaff('Teacher One');
        $driver = Staff::query()->create([
            'employee_code' => Staff::nextEmployeeCode(),
            'full_name' => 'Driver One',
            'role_label' => StaffRoleLabel::Driver,
            'status' => StaffStatus::Active,
            'date_joined' => now()->toDateString(),
        ]);
        $date = now()->toDateString();

        $this->actingAs($admin)
            ->get(route('staff-attendance.index', ['role' => 'teacher', 'date' => $date]))
            ->assertOk()
            ->assertSee($teacher->full_name)
            ->assertDontSee($driver->full_name);

        $this->actingAs($admin)
            ->post(route('staff-attendance.store'), [
                'date' => $date,
                'role' => 'teacher',
                'statuses' => [
                    $teacher->id => 'present',
                    $driver->id => 'absent',
                ],
            ])
            ->assertRedirect(route('staff-attendance.index', ['date' => $date, 'role' => 'teacher']));

        $this->assertTrue(
            StaffAttendanceRecord::query()
                ->where('staff_id', $teacher->id)
                ->whereDate('date', $date)
                ->where('status', 'present')
                ->where('source', 'manual')
                ->exists()
        );
        $this->assertFalse(
            StaffAttendanceRecord::query()
                ->where('staff_id', $driver->id)
                ->whereDate('date', $date)
                ->exists()
        );
    }

    public function test_manual_mark_preserves_webauthn_punch_source_and_rejects_future_date(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = $this->makeStaff();
        $date = now()->toDateString();
        $checkIn = now()->setTime(7, 40);

        StaffAttendanceRecord::query()->create([
            'staff_id' => $staff->id,
            'date' => $date,
            'status' => 'present',
            'source' => 'webauthn',
            'check_in_at' => $checkIn,
            'marked_by' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('staff-attendance.store'), [
                'date' => $date,
                'role' => 'teacher',
                'statuses' => [$staff->id => 'late'],
            ])
            ->assertRedirect();

        $record = StaffAttendanceRecord::query()
            ->where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('late', $record->status->value);
        $this->assertSame('webauthn', $record->source->value);
        $this->assertTrue($record->check_in_at->equalTo($checkIn));

        $this->actingAs($admin)
            ->post(route('staff-attendance.store'), [
                'date' => now()->addDay()->toDateString(),
                'statuses' => [$staff->id => 'present'],
            ])
            ->assertSessionHasErrors('date');
    }

    public function test_checkin_rejected_off_school_network(): void
    {
        SchoolSetting::set('staff_attendance_allowed_cidrs', '10.0.0.0/8');
        $staff = $this->makeStaff();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get(route('staff-checkin.show', $staff->checkin_token))
            ->assertForbidden();
    }

    public function test_webauthn_punch_check_in_and_check_out(): void
    {
        SchoolSetting::set('staff_attendance_allowed_cidrs', '127.0.0.1');
        SchoolSetting::set('staff_attendance_checkin_start', '07:00');
        SchoolSetting::set('staff_attendance_late_after', '08:00');
        SchoolSetting::set('staff_attendance_checkout_time', '16:00');
        $staff = $this->makeStaff();
        $token = $staff->checkin_token;
        $credId = 'test-cred-'.$staff->id;

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('staff-checkin.show', $token))
            ->assertOk()
            ->assertSee('Enroll biometric');

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.register.options', $token))
            ->assertOk()
            ->assertJsonStructure(['challenge', 'rp', 'user']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.register.verify', $token), [
                'credential' => ['id' => $credId, 'type' => 'public-key'],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('staff_webauthn_credentials', [
            'staff_id' => $staff->id,
            'credential_id' => $credId,
        ]);

        $this->travelTo(now()->setTime(7, 30));

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.login.options', $token))
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.login.verify', $token), [
                'credential' => ['id' => $credId],
            ])
            ->assertOk()
            ->assertJsonPath('action', 'check_in')
            ->assertJsonPath('status', 'present');

        $this->assertSame('check_out', StaffAttendancePunch::nextAction($staff->fresh()));

        $this->travelTo(now()->setTime(16, 5));

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.login.options', $token))
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.login.verify', $token), [
                'credential' => ['id' => $credId],
            ])
            ->assertOk()
            ->assertJsonPath('action', 'check_out');

        $record = StaffAttendanceRecord::query()->where('staff_id', $staff->id)->first();
        $this->assertNotNull($record?->check_in_at);
        $this->assertNotNull($record?->check_out_at);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.login.options', $token))
            ->assertOk();

        $this->withExceptionHandling();
        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.login.verify', $token), [
                'credential' => ['id' => $credId],
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['punch']);
    }

    public function test_late_check_in_marked_late(): void
    {
        SchoolSetting::set('staff_attendance_allowed_cidrs', '127.0.0.1');
        SchoolSetting::set('staff_attendance_checkin_start', '07:00');
        SchoolSetting::set('staff_attendance_late_after', '08:00');
        SchoolSetting::set('staff_attendance_checkout_time', '16:00');
        $staff = $this->makeStaff();
        $credId = 'late-cred';

        StaffWebauthnCredential::query()->create([
            'staff_id' => $staff->id,
            'credential_id' => $credId,
            'public_key' => base64_encode('x'),
            'sign_count' => 0,
            'transports' => ['internal'],
            'user_handle' => base64_encode('staff:'.$staff->id),
        ]);

        // Seed challenge for assertion via options call
        $this->travelTo(now()->setTime(9, 15));

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.login.options', $staff->checkin_token))
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('staff-checkin.login.verify', $staff->checkin_token), [
                'credential' => ['id' => $credId],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'late');
    }

    public function test_regenerate_token_invalidates_old_url(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = $this->makeStaff();
        $old = $staff->checkin_token;

        $this->actingAs($admin)
            ->post(route('staff.checkin-link', $staff))
            ->assertRedirect();

        $staff->refresh();
        $this->assertNotSame($old, $staff->checkin_token);

        SchoolSetting::set('staff_attendance_allowed_cidrs', '127.0.0.1');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('staff-checkin.show', $old))
            ->assertNotFound();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('staff-checkin.show', $staff->checkin_token))
            ->assertOk();
    }

    public function test_reset_biometric_clears_credentials(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $staff = $this->makeStaff();
        StaffWebauthnCredential::query()->create([
            'staff_id' => $staff->id,
            'credential_id' => 'to-clear',
            'public_key' => base64_encode('x'),
            'sign_count' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('staff.reset-biometric', $staff))
            ->assertRedirect();

        $this->assertDatabaseMissing('staff_webauthn_credentials', [
            'staff_id' => $staff->id,
        ]);
    }

    public function test_admin_can_save_checkin_start_late_and_checkout_times(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('settings.staff-attendance'), [
                'staff_attendance_allowed_cidrs' => '127.0.0.1',
                'staff_attendance_checkin_start' => '07:15',
                'staff_attendance_late_after' => '08:15',
                'staff_attendance_checkout_time' => '15:30',
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'checkin']));

        $this->assertSame('07:15', StaffAttendancePunch::checkinStartTime());
        $this->assertSame('08:15', StaffAttendancePunch::lateAfterTime());
        $this->assertSame('15:30', StaffAttendancePunch::checkoutTime());
    }

    public function test_checkin_before_start_and_checkout_before_time_are_rejected(): void
    {
        SchoolSetting::set('staff_attendance_checkin_start', '07:00');
        SchoolSetting::set('staff_attendance_late_after', '08:00');
        SchoolSetting::set('staff_attendance_checkout_time', '16:00');
        $staff = $this->makeStaff();

        $this->travelTo(now()->setTime(6, 30));
        try {
            StaffAttendancePunch::punch($staff, now());
            $this->fail('Expected early check-in to fail');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Check-in opens at 07:00', $e->getMessage());
        }

        $this->travelTo(now()->setTime(7, 30));
        StaffAttendancePunch::punch($staff, now());

        $this->travelTo(now()->setTime(15, 0));
        try {
            StaffAttendancePunch::punch($staff, now());
            $this->fail('Expected early check-out to fail');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Check-out opens at 16:00', $e->getMessage());
        }
    }
}

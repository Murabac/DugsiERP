<?php

namespace Tests\Feature;

use App\Enums\StaffAttendanceStatus;
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

    public function test_admin_can_mark_staff_roster_teacher_and_finance_cannot(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $finance = User::factory()->role(UserRole::Finance)->create();
        $staff = $this->makeStaff();

        $this->actingAs($teacher)->get(route('staff-attendance.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('staff-attendance.index'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('staff-attendance.index'))
            ->assertOk()
            ->assertSee($staff->full_name);

        $date = now()->toDateString();
        $this->actingAs($admin)
            ->post(route('staff-attendance.store'), [
                'date' => $date,
                'statuses' => [
                    $staff->id => StaffAttendanceStatus::Present->value,
                ],
            ])
            ->assertRedirect(route('staff-attendance.index', ['date' => $date]));

        $this->assertTrue(
            StaffAttendanceRecord::query()
                ->where('staff_id', $staff->id)
                ->whereDate('date', $date)
                ->where('status', 'present')
                ->where('source', 'manual')
                ->exists()
        );
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
        SchoolSetting::set('staff_attendance_late_after', '08:00');
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
        SchoolSetting::set('staff_attendance_late_after', '08:00');
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
}

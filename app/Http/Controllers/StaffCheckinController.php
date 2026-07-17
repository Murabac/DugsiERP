<?php

namespace App\Http\Controllers;

use App\Enums\StaffStatus;
use App\Enums\StaffAttendanceSource;
use App\Models\Staff;
use App\Support\StaffAttendancePunch;
use App\Support\StaffCheckinNetwork;
use App\Support\StaffWebAuthn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffCheckinController extends Controller
{
    public function show(string $token): View
    {
        $staff = $this->resolveStaff($token);
        StaffCheckinNetwork::assertAllowed(request());

        $staff->load('webauthnCredentials');
        $enrolled = $staff->webauthnCredentials->isNotEmpty();
        $next = StaffAttendancePunch::nextAction($staff);

        return view('staff-checkin.show', [
            'staff' => $staff,
            'token' => $token,
            'enrolled' => $enrolled,
            'nextAction' => $next,
            'schoolName' => \App\Models\SchoolSetting::schoolName(),
            'onSchoolNetwork' => true,
            'allowLocalPunch' => app()->environment('local'),
        ]);
    }

    public function registerOptions(Request $request, string $token, StaffWebAuthn $webauthn): JsonResponse
    {
        $staff = $this->resolveStaff($token);
        StaffCheckinNetwork::assertAllowed($request);
        $staff->load('webauthnCredentials');

        return response()->json($webauthn->creationOptions($staff));
    }

    public function registerVerify(Request $request, string $token, StaffWebAuthn $webauthn): JsonResponse
    {
        $staff = $this->resolveStaff($token);
        StaffCheckinNetwork::assertAllowed($request);

        $data = $request->validate([
            'credential' => ['required', 'array'],
        ]);

        $webauthn->completeRegistration($staff, $data['credential']);

        return response()->json([
            'ok' => true,
            'message' => 'Biometric enrolled. You can check in now.',
        ]);
    }

    public function loginOptions(Request $request, string $token, StaffWebAuthn $webauthn): JsonResponse
    {
        $staff = $this->resolveStaff($token);
        StaffCheckinNetwork::assertAllowed($request);
        $staff->load('webauthnCredentials');

        return response()->json($webauthn->requestOptions($staff));
    }

    public function loginVerify(Request $request, string $token, StaffWebAuthn $webauthn): JsonResponse
    {
        $staff = $this->resolveStaff($token);
        StaffCheckinNetwork::assertAllowed($request);

        $data = $request->validate([
            'credential' => ['required', 'array'],
        ]);

        $webauthn->completeAssertion($staff, $data['credential']);

        return $this->punchResponse($staff);
    }

    /**
     * Local/dev only: punch without WebAuthn when the phone opens http://LAN-IP
     * (browsers block biometrics on non-HTTPS origins except localhost).
     */
    public function localPunch(Request $request, string $token): JsonResponse
    {
        abort_unless(app()->environment('local'), 404);

        $staff = $this->resolveStaff($token);
        StaffCheckinNetwork::assertAllowed($request);

        return $this->punchResponse($staff, StaffAttendanceSource::Mobile);
    }

    private function punchResponse(\App\Models\Staff $staff, StaffAttendanceSource $source = StaffAttendanceSource::Webauthn): JsonResponse
    {
        try {
            $result = StaffAttendancePunch::punch($staff, source: $source);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Unable to punch.',
                'errors' => $e->errors(),
            ], 422);
        }

        $message = $result['action'] === 'check_in'
            ? 'Checked in · '.$result['record']->status->label()
            : 'Checked out at '.now()->format('H:i');

        return response()->json([
            'ok' => true,
            'action' => $result['action'],
            'status' => $result['record']->status->value,
            'message' => $message,
            'next_action' => StaffAttendancePunch::nextAction($staff),
        ]);
    }

    private function resolveStaff(string $token): Staff
    {
        abort_unless(strlen($token) >= 32, 404);

        $staff = Staff::query()
            ->where('checkin_token', $token)
            ->where('status', StaffStatus::Active)
            ->first();

        abort_unless($staff !== null, 404);

        return $staff;
    }
}

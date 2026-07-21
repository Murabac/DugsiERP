<?php

namespace App\Http\Controllers;

use App\Enums\StaffStatus;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\SchoolSetting;
use App\Models\Staff;
use App\Support\AcademicYear;
use App\Support\Money;
use App\Support\PayrollGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $runs = PayrollRun::query()
            ->with(['generatedBy', 'confirmedBy'])
            ->latest('billing_month')
            ->latest('id')
            ->get();

        $staff = Staff::query()
            ->where('status', StaffStatus::Active)
            ->whereNotNull('fixed_salary_usd')
            ->where('fixed_salary_usd', '>', 0)
            ->orderBy('full_name')
            ->get();

        $monthlyTotal = Money::round($staff->sum(fn (Staff $s) => (float) $s->fixed_salary_usd));
        $lastRun = $runs->first();
        $defaultMonth = now()->format('Y-m');
        $month = $request->query('month', $request->old('month', $defaultMonth));
        if (! is_string($month) || preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            $month = $defaultMonth;
        }

        $staffIds = $staff->pluck('id');
        $latestPayslipIds = PayrollItem::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('staff_id', $staffIds)
            ->groupBy('staff_id')
            ->pluck('id');
        $lastPayslipByStaff = PayrollItem::query()
            ->whereIn('id', $latestPayslipIds)
            ->with('payrollRun')
            ->get()
            ->keyBy('staff_id');

        $preview = null;
        $previewError = null;
        $showGenerate = $request->boolean('generate') || filled($request->old('month'));
        if ($showGenerate) {
            try {
                $preview = PayrollGenerator::preview($month);
            } catch (ValidationException $e) {
                $previewError = collect($e->errors())->flatten()->first();
            }
        }

        return view('payroll.index', [
            'runs' => $runs,
            'staff' => $staff,
            'monthlyTotal' => $monthlyTotal,
            'lastRun' => $lastRun,
            'lastPayslipByStaff' => $lastPayslipByStaff,
            'academicYear' => AcademicYear::current(),
            'defaultMonth' => $month,
            'preview' => $preview,
            'previewError' => $previewError,
            'showGenerate' => $showGenerate,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $month = $request->query('month', now()->format('Y-m'));
        if (! is_string($month) || preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return redirect()
                ->route('payroll.index')
                ->withErrors(['month' => 'Select a valid payroll month (YYYY-MM).']);
        }

        return redirect()->route('payroll.index', [
            'generate' => 1,
            'month' => $month,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string', 'max:255'],
            'expected_count' => ['required', 'integer', 'min:1'],
            'expected_total' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $run = PayrollGenerator::confirm(
                $data['month'].'-01',
                $request->user(),
                trim((string) ($data['notes'] ?? '')) ?: null,
                (int) $data['expected_count'],
                Money::round($data['expected_total']),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('payroll.index', ['generate' => 1])
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('payroll.show', $run)
            ->with('status', 'Payroll for '.$run->billing_month->format('F Y').' confirmed — '
                .$run->staff_count.' staff, '.Money::format($run->total_amount).' total.');
    }

    public function show(PayrollRun $payrollRun): View
    {
        $payrollRun->load(['items' => fn ($q) => $q->orderBy('full_name'), 'generatedBy', 'confirmedBy']);

        return view('payroll.show', [
            'run' => $payrollRun,
            'academicYear' => AcademicYear::current(),
        ]);
    }

    public function printRun(PayrollRun $payrollRun): View
    {
        $payrollRun->load(['items' => fn ($q) => $q->orderBy('full_name'), 'confirmedBy']);

        return view('payroll.print-run', [
            'run' => $payrollRun,
            'academicYear' => AcademicYear::current(),
        ]);
    }

    public function payslip(PayrollRun $payrollRun, PayrollItem $payrollItem): View
    {
        abort_unless((int) $payrollItem->payroll_run_id === (int) $payrollRun->id, 404);

        $payrollItem->load('staff');
        $payrollRun->load('confirmedBy');

        return view('payroll.payslip', [
            'run' => $payrollRun,
            'item' => $payrollItem,
            'schoolName' => SchoolSetting::schoolName(),
            'schoolLetterheadSub' => SchoolSetting::schoolLetterheadSub(),
        ]);
    }
}

@extends('layouts.app')

@section('title', $student->full_name.' — Dugsi ERP')

@section('content')
@php
    $isFinanceOnly = auth()->user()?->isFinance() && ! auth()->user()?->isAdmin();
    $profileTabs = $isFinanceOnly
        ? ['overview', 'guardians']
        : ['overview', 'guardians', 'attendance', 'grades'];
    if ($canSeeFees ?? false) {
        $profileTabs[] = 'fees';
    }
    if ($canSeeDocuments ?? false) {
        $profileTabs[] = 'documents';
    }
    if ($canSeeTransport ?? false) {
        $profileTabs[] = 'transport';
    }
    $activeTab = in_array($tab, $profileTabs, true) ? $tab : 'overview';
    $className = $schoolClass?->displayName() ?? '—';
    $transportAssignment = $student->activeTransportAssignment;
@endphp

<div class="space-y-4">
    <x-breadcrumb :items="array_values(array_filter([
        ['label' => 'Classes', 'url' => route('classes.index')],
        $schoolClass ? ['label' => $className, 'url' => route('classes.roster', $schoolClass)] : null,
        $schoolClass ? ['label' => 'Student Roster', 'url' => route('classes.roster', $schoolClass)] : null,
        ['label' => $student->full_name],
    ]))" />

    @if ($waitlist)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            On waitlist for <strong>{{ $waitlist->schoolClass?->displayName() }}</strong>
            (position {{ $waitlist->position }}).
            An admin can enroll this student from the class roster after increasing capacity.
            <a href="{{ route('classes.roster', $waitlist->schoolClass) }}" class="ml-1 font-semibold underline">Open roster</a>
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex items-start gap-4">
            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-100 text-lg font-bold text-dugsi-primary">
                @if ($student->photoUrl())
                    <img src="{{ $student->photoUrl() }}" alt="{{ $student->full_name }}" class="h-full w-full object-cover">
                @else
                    {{ $student->initials() }}
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ $student->full_name }}</h3>
                        <div class="mt-0.5 font-mono text-xs text-slate-500">{{ $student->student_code }} · {{ $className }}</div>
                    </div>
                    <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                        <x-status-badge :status="$student->status" />
                        @if (auth()->user()?->isAdmin())
                            <x-btn variant="secondary" size="sm" href="{{ route('students.edit', $student) }}">Edit</x-btn>
                        @endif
                    </div>
                </div>
                <div class="mt-2.5 flex flex-wrap gap-4 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1"><x-icon name="calendar" :size="12" /> DOB {{ $student->dob->format('j M Y') }}</span>
                    <span class="inline-flex items-center gap-1"><x-icon name="layers" :size="12" /> {{ $student->city ?? '—' }}</span>
                    <span class="inline-flex items-center gap-1"><x-icon name="users" :size="12" /> {{ $student->primaryGuardian?->phone ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="flex w-full max-w-full gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 sm:w-fit">
        @foreach ($profileTabs as $t)
            <a href="{{ route('students.show', ['student' => $student, 'tab' => $t]) }}"
                class="whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-medium capitalize transition-colors {{ $activeTab === $t ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                {{ $t }}
            </a>
        @endforeach
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-5">
        @if ($activeTab === 'overview')
            <div class="grid grid-cols-1 gap-6 text-sm md:grid-cols-2">
                <div>
                    <h4 class="mb-3 text-xs font-semibold tracking-wider text-slate-700 uppercase">Personal Details</h4>
                    <div class="space-y-2">
                        @foreach ([
                            ['Full Name', $student->full_name],
                            ['Student ID', $student->student_code],
                            ['DOB', $student->dob->format('Y-m-d')],
                            ['Gender', $student->gender->label()],
                            ['City', $student->city ?? '—'],
                            ['Guardian', $student->primaryGuardian?->full_name ?? '—'],
                        ] as [$k, $v])
                            <div class="flex gap-2">
                                <span class="w-24 flex-shrink-0 text-xs text-slate-400">{{ $k }}</span>
                                <span class="text-xs font-medium text-slate-800">{{ $v }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h4 class="mb-3 text-xs font-semibold tracking-wider text-slate-700 uppercase">Enrollment</h4>
                    <div class="space-y-2">
                        @php
                            $enrollmentRows = [
                                ['Class', $className],
                                ['Academic Year', $enrollment?->academic_year ?? $waitlist?->academic_year ?? '—'],
                                ['Roll No.', $enrollment ? str_pad((string) $enrollment->roll_number, 2, '0', STR_PAD_LEFT) : ($waitlist ? 'Waitlist #'.$waitlist->position : '—')],
                                ['Enrolled', $enrollment?->enrollment_date?->format('d M Y') ?? '—'],
                                ['Status', $student->status->label()],
                            ];
                            if ($canSeeFees ?? false) {
                                $enrollmentRows[] = ['Need-based fee discount', $student->need_based_discount ? 'Yes' : 'No'];
                            }
                        @endphp
                        @foreach ($enrollmentRows as [$k, $v])
                            <div class="flex gap-2">
                                <span class="w-36 flex-shrink-0 text-xs text-slate-400">{{ $k }}</span>
                                <span class="text-xs font-medium text-slate-800">{{ $v }}</span>
                            </div>
                        @endforeach
                        @if (auth()->user()->isAdmin())
                            <form method="POST" action="{{ route('students.need-based', $student) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="need_based_discount" value="{{ $student->need_based_discount ? '0' : '1' }}">
                                <button type="submit" class="text-xs font-medium text-blue-700 hover:underline">
                                    {{ $student->need_based_discount ? 'Remove need-based discount' : 'Enable need-based discount' }}
                                </button>
                            </form>
                        @endif
                    </div>
                    <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div class="text-[11px] font-medium tracking-wide text-slate-500 uppercase">Recent Attendance</div>
                        @if ($attendanceRate !== null)
                            <div class="mt-0.5 text-2xl font-bold text-green-800">{{ $attendanceRate }}%</div>
                            <div class="text-[11px] text-slate-400">Last {{ $attendanceHistory->count() }} recorded days</div>
                        @else
                            <div class="mt-0.5 text-sm text-slate-400">No attendance recorded yet</div>
                        @endif
                    </div>
                </div>
            </div>
        @elseif ($activeTab === 'guardians')
            <div class="space-y-3">
                <h4 class="text-xs font-semibold tracking-wider text-slate-700 uppercase">Guardians</h4>
                @error('guardian')
                    <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</p>
                @enderror
                @foreach ($student->guardians as $guardian)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3.5">
                        <div class="flex items-start gap-3">
                            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-dugsi-primary text-xs font-bold text-white">
                                {{ $guardian->initials() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-slate-800">{{ $guardian->full_name }}</div>
                                <div class="mt-0.5 text-xs text-slate-500">{{ $guardian->relationship->label() }}{{ $guardian->is_primary ? ' · Primary Contact' : '' }}</div>
                                <div class="mt-1 text-sm text-slate-600">{{ $guardian->phone }}</div>
                            </div>
                            @if ($guardian->is_primary)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-800">Primary</span>
                            @endif
                        </div>

                        @if (auth()->user()?->isAdmin())
                            <details class="mt-3 border-t border-slate-200 pt-3">
                                <summary class="cursor-pointer text-xs font-medium text-dugsi-primary">Edit guardian</summary>
                                <form method="POST" action="{{ route('students.guardians.update', [$student, $guardian]) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Full Name</label>
                                        <input name="full_name" value="{{ old('full_name', $guardian->full_name) }}" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Phone</label>
                                        <input name="phone" value="{{ old('phone', $guardian->phone) }}" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Relationship</label>
                                        <select name="relationship" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                            @foreach (\App\Enums\GuardianRelationship::cases() as $r)
                                                <option value="{{ $r->value }}" @selected(old('relationship', $guardian->relationship->value) === $r->value)>{{ $r->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <label class="col-span-2 flex items-center gap-2 text-xs text-slate-600">
                                        <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $guardian->is_primary)) class="rounded border-slate-300">
                                        Set as primary contact
                                    </label>
                                    <div class="col-span-2 flex flex-wrap gap-2">
                                        <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Save</button>
                                    </div>
                                </form>
                                @if ($student->guardians->count() > 1)
                                    <form method="POST" action="{{ route('students.guardians.destroy', [$student, $guardian]) }}" class="mt-2"
                                        onsubmit="return confirm('Remove this guardian?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Remove guardian</button>
                                    </form>
                                @endif
                            </details>
                        @endif
                    </div>
                @endforeach

                @if (auth()->user()?->isAdmin())
                <details class="rounded-lg border border-slate-200 bg-white p-3">
                    <summary class="cursor-pointer text-sm font-medium text-dugsi-primary">+ Add Guardian</summary>
                    <form method="POST" action="{{ route('students.guardians.store', $student) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @csrf
                        <div class="col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-700">Full Name</label>
                            <input name="full_name" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">Phone</label>
                            <input name="phone" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">Relationship</label>
                            <select name="relationship" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                @foreach (\App\Enums\GuardianRelationship::cases() as $r)
                                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="col-span-2 flex items-center gap-2 text-xs text-slate-600">
                            <input type="checkbox" name="is_primary" value="1" class="rounded border-slate-300">
                            Set as primary contact
                        </label>
                        <div class="col-span-2">
                            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Save Guardian</button>
                        </div>
                    </form>
                </details>
                @endif
            </div>
        @elseif ($activeTab === 'attendance')
            <div>
                <h4 class="mb-3 text-xs font-semibold tracking-wider text-slate-700 uppercase">
                    Attendance History
                    @if ($attendanceRate !== null)
                        <span class="ml-2 font-medium normal-case text-slate-400">· {{ $attendanceRate }}% present/late (last {{ $attendanceHistory->count() }} days)</span>
                    @endif
                </h4>
                @if ($attendanceHistory->isEmpty())
                    <p class="py-8 text-center text-sm text-slate-400">No attendance records yet for this student.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[360px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50">
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Date</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attendanceHistory as $record)
                                    <tr class="border-b border-slate-50">
                                        <td class="px-3 py-2 text-slate-700">{{ $record->date->format('D, j M Y') }}</td>
                                        <td class="px-3 py-2">
                                            <span class="text-xs font-semibold {{ $record->status->toneClass() }}">
                                                {{ $record->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-500">{{ $record->reason ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @elseif ($activeTab === 'grades')
            <div class="px-4 py-3">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">
                        Grades
                        <span class="ml-1 font-medium normal-case text-slate-400">· {{ $academicYear }}</span>
                    </h3>
                    <form method="GET" action="{{ route('students.show', $student) }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="grades">
                        <label class="text-xs text-slate-500">Term</label>
                        <select name="term" onchange="this.form.submit()" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                            @foreach ($gradeTerms as $t)
                                <option value="{{ $t->value }}" @selected($gradeTerm === $t)>{{ $t->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if (! $enrollment)
                    <p class="py-8 text-center text-sm text-slate-400">No active enrollment — grades appear after the student is placed in a class.</p>
                @elseif ($studentGrades->isEmpty())
                    <p class="py-8 text-center text-sm text-slate-400">No grades recorded for {{ $gradeTerm->label() }} yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[480px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50">
                                    @foreach (['Subject', 'Score', 'Grade', 'Remarks'] as $h)
                                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($studentGrades as $grade)
                                    <tr class="border-b border-slate-50">
                                        <td class="px-3 py-2 font-medium text-slate-900">{{ $grade->subject?->name }}</td>
                                        <td class="px-3 py-2">{{ number_format((float) $grade->score_percent, 1) }}%</td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-bold {{ $grade->letter_grade->badgeClass() }}">{{ $grade->letter_grade->value }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-500">{{ $grade->remarks ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($schoolClass && auth()->user()->canGenerateGradeReport($schoolClass))
                        <div class="mt-3">
                            <a href="{{ route('grades.report', ['class' => $schoolClass->id, 'student' => $student->id, 'term' => $gradeTerm->value]) }}"
                                class="text-xs font-medium text-blue-700 hover:underline">
                                Open full grade report →
                            </a>
                        </div>
                    @endif
                @endif
            </div>
        @elseif ($activeTab === 'fees')
            <div class="px-4 py-3">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-700">
                    Fees
                    <span class="ml-1 font-medium normal-case text-slate-400">· {{ $academicYear }}</span>
                </h3>
                @if ($studentInvoices->isEmpty())
                    <p class="py-8 text-center text-sm text-slate-400">No invoices for this academic year yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50">
                                    @foreach (['Invoice', 'Month', 'Due', 'Paid', 'Balance', 'Status'] as $h)
                                        <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($studentInvoices as $invoice)
                                    <tr class="border-b border-slate-50">
                                        <td class="px-3 py-2 font-mono text-xs text-slate-600">{{ $invoice->invoice_number }}</td>
                                        <td class="px-3 py-2">
                                            {{ $invoice->billing_month->format('M Y') }}
                                            @if ((float) $invoice->transport_fee > 0)
                                                <div class="text-[10px] text-slate-400">
                                                    Tuition {{ \App\Support\Money::format((float) $invoice->amount_due - (float) $invoice->transport_fee) }}
                                                    + Transport {{ \App\Support\Money::format($invoice->transport_fee) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($invoice->amount_due) }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ \App\Support\Money::format($invoice->amount_paid) }}</td>
                                        <td class="px-3 py-2 tabular-nums font-medium">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span>
                                        </td>
                                    </tr>
                                    @if ($invoice->payments->isNotEmpty())
                                        <tr class="border-b border-slate-50 bg-slate-50/60">
                                            <td colspan="6" class="px-3 py-2 text-[11px] text-slate-500">
                                                @foreach ($invoice->payments as $payment)
                                                    <div>
                                                        {{ $payment->paid_at?->format('j M Y') }}
                                                        · {{ \App\Support\Money::format($payment->amount) }}
                                                        · {{ $payment->method->label() }}
                                                        · @if (auth()->user()->isAdmin() || auth()->user()->hasRole(\App\Enums\UserRole::Finance))
                                                            <a href="{{ route('finance.payments.receipt', $payment) }}" class="font-medium text-blue-700 hover:underline">{{ $payment->receipt_number }}</a>
                                                        @else
                                                            {{ $payment->receipt_number }}
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
                @elseif ($activeTab === 'documents')
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs text-slate-500">Generated documents for this student.</p>
                    @if (auth()->user()?->isAdmin())
                        <a href="{{ route('documents.index', ['tab' => 'generate']) }}" class="text-xs font-medium text-blue-700 hover:underline">Generate new</a>
                    @endif
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full min-w-[520px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50">
                                <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Doc</th>
                                <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Type</th>
                                <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Generated</th>
                                <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($studentDocuments as $doc)
                                <tr class="border-b border-slate-50">
                                    <td class="px-3 py-2 font-mono text-xs">{{ $doc->document_number }}</td>
                                    <td class="px-3 py-2">{{ $doc->document_type->label() }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $doc->generated_at?->format('j M Y') }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @if (auth()->user()?->isAdmin())
                                            <a href="{{ route('documents.print', $doc) }}" target="_blank" rel="noopener" class="text-xs font-medium text-blue-700 hover:underline">View</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-400">No documents yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($activeTab === 'transport')
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Transport</h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Flat fee {{ \App\Support\Money::format($transportFeeUsd ?? 0) }}/month on the tuition invoice when assigned.
                        </p>
                    </div>
                    <a href="{{ route('transport.assignments.index') }}" class="text-xs font-medium text-blue-700 hover:underline">All assignments</a>
                </div>

                @if ($transportAssignment)
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div><span class="text-xs text-slate-400">Bus</span><div class="font-medium text-slate-900">{{ $transportAssignment->route?->displayName() }}</div></div>
                            <div><span class="text-xs text-slate-400">Plate</span><div class="font-medium text-slate-900">{{ $transportAssignment->route?->vehicle?->plate_number ?? '—' }}</div></div>
                            <div><span class="text-xs text-slate-400">Driver</span><div class="font-medium text-slate-900">{{ $transportAssignment->route?->vehicle?->driver?->full_name ?? '—' }}</div></div>
                            <div><span class="text-xs text-slate-400">Since</span><div class="font-medium text-slate-900">{{ $transportAssignment->started_on?->format('j M Y') }}</div></div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-3">
                            @if ($transportAssignment->route)
                                <a href="{{ route('transport.buses.show', $transportAssignment->route) }}" class="text-xs font-medium text-blue-700 hover:underline">Open bus</a>
                            @endif
                            <form method="POST" action="{{ route('transport.assignments.end', $transportAssignment) }}"
                                onsubmit="return confirm('End transport for this student?')">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline">End assignment</button>
                            </form>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-slate-500">Not on transport this year.</p>
                    @if (($transportRoutes ?? collect())->isNotEmpty())
                        <form method="POST" action="{{ route('transport.assignments.store') }}" class="max-w-md space-y-3 rounded-md border border-slate-200 p-4">
                            @csrf
                            <input type="hidden" name="student_id" value="{{ $student->id }}">
                            <input type="hidden" name="redirect_to" value="{{ route('students.show', ['student' => $student, 'tab' => 'transport']) }}">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-700">Bus</label>
                                <select name="route_id" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select…</option>
                                    @foreach ($transportRoutes as $r)
                                        <option value="{{ $r->id }}">{{ $r->displayName() }} ({{ $r->active_assignments_count }}/{{ $r->capacity() }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Assign to transport</button>
                        </form>
                    @else
                        <p class="text-xs text-slate-400">Register a bus first under Transport.</p>
                    @endif
                @endif
            </div>
        @else
            <div class="py-8 text-center text-slate-400">
                <p class="text-sm font-medium text-slate-500 capitalize">{{ $activeTab }} coming soon</p>
                <p class="mt-1 text-xs">This tab will be filled when the related module is built.</p>
            </div>
        @endif
    </div>
</div>
@endsection

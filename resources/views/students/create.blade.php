@extends('layouts.app')

@section('title', 'Add Student — Dugsi ERP')

@section('content')
@php
    $step = (int) old('step', 1);
    $steps = ['Personal Info', 'Enrollment', 'Guardian'];
@endphp

<div class="max-w-xl space-y-4">
    <x-breadcrumb :items="array_values(array_filter([
        ['label' => 'Classes', 'url' => route('classes.index')],
        $preselectedClass ? ['label' => $preselectedClass->displayName(), 'url' => route('classes.roster', $preselectedClass)] : null,
        ['label' => 'Add New Student'],
    ]))" />

    <div>
        <h2 class="text-base font-semibold text-slate-900">Add New Student</h2>
        <p class="mt-0.5 text-xs text-slate-500">Step <span id="step-num">1</span> of 3</p>
    </div>

    <div class="flex flex-wrap gap-1.5" id="step-pills">
        @foreach ($steps as $i => $label)
            <div data-pill="{{ $i + 1 }}" class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium transition-colors sm:px-3 sm:text-xs {{ $i === 0 ? 'bg-dugsi-primary text-white' : 'bg-slate-100 text-slate-500' }}">
                <span>{{ $i + 1 }}</span><span class="sm:inline">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('students.store') }}" enctype="multipart/form-data" id="admit-form">
        @csrf
        <input type="hidden" name="step" id="step-input" value="1">

        <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-5">
            {{-- Step 1 --}}
            <div data-step-panel="1" class="space-y-3">
                <h3 class="border-b border-slate-100 pb-2 text-xs font-semibold tracking-wider text-slate-700 uppercase">Personal Information</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Full Name <span class="text-red-500">*</span></label>
                        <input name="full_name" value="{{ old('full_name') }}" required placeholder="e.g. Faadumo Xasan Warsame"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @error('full_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <x-dob-select
                        name="dob"
                        :value="old('dob')"
                        :min-year="$dobMinYear"
                        :max-year="$dobMaxYear"
                        :default="$dobDefault"
                    />

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Gender <span class="text-red-500">*</span></label>
                        <select name="gender" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                            <option value="">Select</option>
                            @foreach ($genders as $g)
                                <option value="{{ $g->value }}" @selected(old('gender') === $g->value)>{{ $g->label() }}</option>
                            @endforeach
                        </select>
                        @error('gender')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">City</label>
                        <select name="city" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                            <option value="">Select city</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city }}" @selected(old('city', 'Hargeisa') === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                        @error('city')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Previous School</label>
                        <input name="previous_school" value="{{ old('previous_school') }}" placeholder="e.g. Horseed Primary"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Address</label>
                        <input name="address" value="{{ old('address') }}" placeholder="e.g. Sha'ab District, Hargeisa"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Photo Upload</label>
                    <input type="file" name="photo" accept="image/jpeg,image/png"
                        class="block w-full rounded-md border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5">
                    <p class="mt-1 text-[11px] text-slate-400">JPG / PNG, max 2 MB</p>
                    @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Step 2 --}}
            <div data-step-panel="2" class="hidden space-y-3">
                <h3 class="border-b border-slate-100 pb-2 text-xs font-semibold tracking-wider text-slate-700 uppercase">Enrollment Details</h3>
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
                    Class assignment is required before a student can be registered.
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Class / Section <span class="text-red-500">*</span></label>
                        <select name="class_id" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                            <option value="">Select class</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected((string) old('class_id', $preselectedClass?->id) === (string) $class->id)>
                                    {{ $class->displayName() }}
                                </option>
                            @endforeach
                        </select>
                        @error('class_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @if ($classes->isEmpty())
                            <p class="mt-1 text-xs text-amber-700">No active classes for academic year {{ $academicYear }}. Create them under Manage Classes first.</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Academic Year</label>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800">{{ $academicYear }}</div>
                        <p class="mt-1 text-[11px] text-slate-400">Current school year (set automatically)</p>
                    </div>
                    <x-date-select
                        name="enrollment_date"
                        label="Enrollment Date"
                        :value="old('enrollment_date')"
                        :default="now()->toDateString()"
                        :min-year="now()->year - 2"
                        :max-year="now()->year"
                        :required="true"
                        hint="Day · Month · Year"
                    />
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Status</label>
                        <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected(old('status', 'active') === $st->value)>{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Step 3 --}}
            <div data-step-panel="3" class="hidden space-y-3">
                <h3 class="border-b border-slate-100 pb-2 text-xs font-semibold tracking-wider text-slate-700 uppercase">Guardian / Parent Information</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Guardian Full Name <span class="text-red-500">*</span></label>
                        <input name="guardian_name" value="{{ old('guardian_name') }}" required placeholder="e.g. Xasan Warsame Jama"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @error('guardian_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Phone Number <span class="text-red-500">*</span></label>
                        <input name="guardian_phone" value="{{ old('guardian_phone') }}" required placeholder="+252 63 xxx xxxx"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @error('guardian_phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Relationship</label>
                        <select name="relationship" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                            @foreach ($relationships as $r)
                                <option value="{{ $r->value }}" @selected(old('relationship', 'father') === $r->value)>{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-700">
                    Absence alerts and fee reminder SMS will be sent to this number.
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <button type="button" id="btn-back" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="button" id="btn-next" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Next →</button>
            <button type="submit" id="btn-save" class="hidden rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">Save Student</button>
        </div>
    </form>
</div>

<script>
(() => {
    let step = {{ $errors->any() ? 1 : 1 }};
    @if ($errors->hasAny(['class_id', 'enrollment_date', 'status']))
        step = 2;
    @elseif ($errors->hasAny(['guardian_name', 'guardian_phone', 'relationship']))
        step = 3;
    @elseif ($errors->any())
        step = 1;
    @endif

    const panels = [...document.querySelectorAll('[data-step-panel]')];
    const pills = [...document.querySelectorAll('[data-pill]')];
    const btnBack = document.getElementById('btn-back');
    const btnNext = document.getElementById('btn-next');
    const btnSave = document.getElementById('btn-save');
    const stepNum = document.getElementById('step-num');
    const cancelUrl = @json($preselectedClass ? route('classes.roster', $preselectedClass) : route('classes.index'));

    function render() {
        panels.forEach(p => p.classList.toggle('hidden', Number(p.dataset.stepPanel) !== step));
        pills.forEach(p => {
            const n = Number(p.dataset.pill);
            p.className = 'flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors ' +
                (n === step ? 'bg-dugsi-primary text-white' : n < step ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-500');
        });
        stepNum.textContent = step;
        btnBack.textContent = step === 1 ? 'Cancel' : '← Back';
        btnNext.classList.toggle('hidden', step === 3);
        btnSave.classList.toggle('hidden', step !== 3);
    }

    function validateStep(n) {
        const panel = document.querySelector(`[data-step-panel="${n}"]`);
        const required = [...panel.querySelectorAll('[required]')];
        for (const el of required) {
            if (!el.value || (el.type === 'radio' && !el.checked)) {
                el.reportValidity();
                return false;
            }
        }
        return true;
    }

    btnNext.addEventListener('click', () => {
        if (!validateStep(step)) return;
        step = Math.min(3, step + 1);
        render();
    });
    btnBack.addEventListener('click', () => {
        if (step === 1) { window.location.href = cancelUrl; return; }
        step -= 1;
        render();
    });

    render();
})();
</script>
@endsection

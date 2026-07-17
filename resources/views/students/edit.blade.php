@extends('layouts.app')

@section('title', 'Edit '.$student->full_name.' — Dugsi ERP')

@section('content')
@php
    $className = $enrollment?->schoolClass?->displayName() ?? $waitlist?->schoolClass?->displayName();
@endphp

<div class="max-w-xl space-y-4">
    <x-breadcrumb :items="array_values(array_filter([
        ['label' => 'Classes', 'url' => route('classes.index')],
        $enrollment?->schoolClass ? ['label' => $className, 'url' => route('classes.roster', $enrollment->schoolClass)] : null,
        ['label' => $student->full_name, 'url' => route('students.show', $student)],
        ['label' => 'Edit'],
    ]))" />

    <div>
        <h2 class="text-base font-semibold text-slate-900">Edit Student</h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ $student->student_code }} · Update personal details and enrollment</p>
    </div>

    <form method="POST" action="{{ route('students.update', $student) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-5 space-y-3">
            <h3 class="border-b border-slate-100 pb-2 text-xs font-semibold tracking-wider text-slate-700 uppercase">Personal Information</h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-700">Full Name <span class="text-red-500">*</span></label>
                    <input name="full_name" value="{{ old('full_name', $student->full_name) }}" required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                    @error('full_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <x-dob-select
                    name="dob"
                    :value="old('dob', $student->dob?->format('Y-m-d'))"
                    :min-year="$dobMinYear"
                    :max-year="$dobMaxYear"
                    :default="$dobDefault"
                />

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Gender <span class="text-red-500">*</span></label>
                    <select name="gender" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @foreach ($genders as $g)
                            <option value="{{ $g->value }}" @selected(old('gender', $student->gender->value) === $g->value)>{{ $g->label() }}</option>
                        @endforeach
                    </select>
                    @error('gender')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">City</label>
                    <select name="city" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        <option value="">Select city</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city }}" @selected(old('city', $student->city) === $city)>{{ $city }}</option>
                        @endforeach
                    </select>
                    @error('city')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Previous School</label>
                    <input name="previous_school" value="{{ old('previous_school', $student->previous_school) }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-xs font-medium text-slate-700">Address</label>
                    <input name="address" value="{{ old('address', $student->address) }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Photo</label>
                @if ($student->photoUrl())
                    <div class="mb-2 flex items-center gap-3">
                        <img src="{{ $student->photoUrl() }}" alt="" class="h-12 w-12 rounded-full object-cover">
                        <span class="text-xs text-slate-500">Upload a new photo to replace</span>
                    </div>
                @endif
                <input type="file" name="photo" accept="image/jpeg,image/png"
                    class="block w-full rounded-md border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5">
                <p class="mt-1 text-[11px] text-slate-400">JPG / PNG, max 2 MB</p>
                @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-5 space-y-3">
            <h3 class="border-b border-slate-100 pb-2 text-xs font-semibold tracking-wider text-slate-700 uppercase">Enrollment</h3>

            @if ($waitlist && ! $enrollment)
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    This student is on the waitlist for {{ $waitlist->schoolClass?->displayName() }}. Class transfer and status changes are available after enrollment from the roster.
                </div>
                <input type="hidden" name="status" value="{{ \App\Enums\StudentStatus::Waitlisted->value }}">
            @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @if ($enrollment)
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-700">Class / Section</label>
                        <select name="class_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected((string) old('class_id', $enrollment->class_id) === (string) $class->id)>
                                    {{ $class->displayName() }}
                                </option>
                            @endforeach
                        </select>
                        @error('class_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <x-date-select
                        name="enrollment_date"
                        label="Enrollment Date"
                        :value="old('enrollment_date', $enrollment->enrollment_date?->format('Y-m-d'))"
                        :default="$enrollment->enrollment_date?->format('Y-m-d') ?? now()->toDateString()"
                        :min-year="now()->year - 2"
                        :max-year="now()->year"
                        :required="false"
                        hint="Day · Month · Year"
                    />
                @endif
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700">Status</label>
                    <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}" @selected(old('status', $student->status->value) === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="need_based_discount" value="1" @checked(old('need_based_discount', $student->need_based_discount))
                            class="rounded border-slate-300 text-dugsi-primary focus:ring-dugsi-primary">
                        Need-based fee discount
                    </label>
                </div>
            </div>
            @endif

            @if ($waitlist && ! $enrollment)
            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="need_based_discount" value="1" @checked(old('need_based_discount', $student->need_based_discount))
                        class="rounded border-slate-300 text-dugsi-primary focus:ring-dugsi-primary">
                    Need-based fee discount
                </label>
            </div>
            @endif
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <x-btn variant="secondary" href="{{ route('students.show', $student) }}">Cancel</x-btn>
            <x-btn type="submit">Save Changes</x-btn>
        </div>
    </form>
</div>
@endsection

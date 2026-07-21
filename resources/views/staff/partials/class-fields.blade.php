@php
    $selectedClassIds = old('class_ids', $selectedClassIds ?? []);
    if (! is_array($selectedClassIds)) {
        $selectedClassIds = [];
    }
    $selectedClassIds = array_map('intval', $selectedClassIds);
    $classes = $classes ?? collect();
@endphp
<div id="{{ $wrapId ?? 'staff-classes-wrap' }}" @class([
    ($colSpan ?? 'col-span-2'),
    'hidden' => ($hideForFinance ?? false) && ($currentRole ?? 'teacher') === 'finance',
]) data-staff-classes>
    <label class="mb-1.5 block text-xs font-medium text-slate-700">
        Assigned classes
        <span class="font-normal text-slate-400">(optional — classes this teacher works with)</span>
    </label>
    @if ($classes->isEmpty())
        <p class="rounded-md border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
            No active classes for {{ \App\Support\AcademicYear::current() }} yet. Create classes first, then assign them here.
        </p>
    @else
        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
            @foreach ($classes as $class)
                <label class="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                    <input type="checkbox" name="class_ids[]" value="{{ $class->id }}"
                        @checked(in_array((int) $class->id, $selectedClassIds, true))
                        class="rounded border-slate-300 text-dugsi-primary focus:ring-dugsi-primary">
                    <span>{{ $class->displayName() }}</span>
                </label>
            @endforeach
        </div>
    @endif
    @error('class_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    @error('class_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

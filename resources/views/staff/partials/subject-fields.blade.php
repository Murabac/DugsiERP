@php
    $selectedSubjects = old('subjects', $selectedSubjects ?? []);
    if (! is_array($selectedSubjects)) {
        $selectedSubjects = $selectedSubjects ? [(string) $selectedSubjects] : [];
    }
@endphp
<div id="{{ $wrapId ?? 'staff-subject-wrap' }}" @class([
    ($colSpan ?? 'col-span-2'),
    'hidden' => ($hideForFinance ?? false) && ($currentRole ?? 'teacher') === 'finance',
]) data-staff-subjects>
    <label class="mb-1.5 block text-xs font-medium text-slate-700">
        Subjects
        <span class="font-normal text-slate-400" data-subjects-required-hint>{{ ($requireForTeacher ?? true) ? '(required for teachers — select one or more)' : '' }}</span>
    </label>
    <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
        @foreach ($subjects as $sub)
            <label class="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                <input type="checkbox" name="subjects[]" value="{{ $sub }}"
                    @checked(in_array($sub, $selectedSubjects, true))
                    class="rounded border-slate-300 text-dugsi-primary focus:ring-dugsi-primary">
                <span>{{ $sub }}</span>
            </label>
        @endforeach
    </div>
    @error('subjects')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    @error('subjects.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    @error('subject_specialty')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

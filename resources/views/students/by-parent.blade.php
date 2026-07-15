@extends('layouts.app')

@section('title', 'Find by Parent — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-breadcrumb :items="[
        ['label' => 'Classes', 'url' => route('classes.index')],
        ['label' => 'Find by Parent'],
    ]" />

    <div>
        <h2 class="text-base font-semibold text-slate-900">Find Students by Parent</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            Search by guardian name or phone — shows all linked students across classes (siblings share a phone).
        </p>
    </div>

    <form method="GET" action="{{ route('students.by-parent') }}" class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="parent-q" class="mb-1 block text-xs font-medium text-slate-700">Parent name or phone</label>
                <input id="parent-q" type="search" name="q" value="{{ $q }}" autofocus
                    placeholder="e.g. Xasan Warsame or +252 63 400 1234"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
            </div>
            <button type="submit" class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
                Search
            </button>
        </div>
    </form>

    @unless ($searched)
        <div class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-400">
            Enter a parent name or phone number to find their students.
        </div>
    @elseif ($tooShort)
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-400">
            Enter at least 2 characters to search.
        </div>
    @else
        @if ($families->isEmpty())
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-400">
                No guardians matched “{{ $q }}”.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($families as $family)
                    <div class="rounded-lg border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 px-4 py-3">
                            <div class="text-sm font-semibold text-slate-900">{{ $family['parent_name'] }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">
                                {{ $family['parent_phone'] ?: 'No phone' }}
                                @if ($family['relationship'])
                                    · {{ $family['relationship'] }}
                                @endif
                                · {{ $family['students']->count() }} student{{ $family['students']->count() === 1 ? '' : 's' }}
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[520px] text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50">
                                        @foreach (['Student', 'ID', 'Class', 'Status', ''] as $h)
                                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($family['students'] as $row)
                                        @php
                                            /** @var \App\Models\Student $student */
                                            $student = $row['student'];
                                            $class = $row['class'];
                                        @endphp
                                        <tr class="border-b border-slate-50 hover:bg-slate-50">
                                            <td class="px-4 py-2.5">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-[10px] font-bold text-dugsi-primary">
                                                        {{ $student->initials() }}
                                                    </div>
                                                    <span class="font-medium text-slate-900">{{ $student->full_name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400">{{ $student->student_code }}</td>
                                            <td class="px-4 py-2.5 text-slate-600">
                                                @if ($class)
                                                    <a href="{{ route('classes.roster', $class) }}" class="text-blue-700 hover:underline">{{ $class->displayName() }}</a>
                                                @else
                                                    <span class="text-slate-400">No current class</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5"><x-status-badge :status="$student->status" /></td>
                                            <td class="px-4 py-2.5">
                                                <a href="{{ route('students.show', $student) }}" class="text-xs text-blue-700 hover:underline">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endunless
</div>
@endsection

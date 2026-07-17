@extends('layouts.app')

@section('title', $route->name.' — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header
        :title="$route->name"
        :sub="($route->vehicle?->plate_number ?? '—').' · '.($route->vehicle?->driver?->full_name ?? 'No driver').' · '.$route->activeAssignments->count().' / '.$route->capacity().' seats'"
    >
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('transport.buses.print', $route) }}" target="_blank" rel="noopener">
                <x-icon name="printer" :size="14" /> Print roster
            </x-btn>
            <x-btn variant="secondary" href="{{ route('transport.buses.edit', $route) }}">Edit bus</x-btn>
            <x-btn href="{{ route('transport.assignments.index', ['route' => $route->id]) }}">Assign students</x-btn>
        </x-slot:action>
    </x-section-header>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[480px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th class="px-3 py-2">Student</th>
                    <th class="px-3 py-2">Class</th>
                    <th class="px-3 py-2">Phone</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $a)
                    <tr class="border-b border-slate-50">
                        <td class="px-3 py-2">
                            <a href="{{ route('students.show', ['student' => $a->student, 'tab' => 'transport']) }}" class="font-medium text-blue-700 hover:underline">{{ $a->student?->full_name }}</a>
                            <div class="font-mono text-[11px] text-slate-400">{{ $a->student?->student_code }}</div>
                        </td>
                        <td class="px-3 py-2 text-slate-600">{{ $a->student?->currentEnrollment?->schoolClass?->displayName() ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ $a->student?->primaryGuardian?->phone ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-10 text-center text-sm text-slate-400">No students on this bus yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

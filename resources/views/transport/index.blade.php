@extends('layouts.app')

@section('title', 'Transport — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header title="Transport" :sub="'School buses · '.$academicYear">
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('transport.assignments.index') }}">Assign students</x-btn>
            <x-btn href="{{ route('transport.buses.create') }}">Register bus</x-btn>
        </x-slot:action>
    </x-section-header>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
        <x-stat-card label="Active buses" :value="(string) $busCount" />
        <x-stat-card label="Riders" :value="(string) $riders" :sub="$riders.' / '.$seatsTotal.' seats'" />
        <x-stat-card label="Seats free" :value="(string) $seatsFree" />
    </div>

    <p class="text-xs text-slate-500">
        Flat transport fee {{ \App\Support\Money::format($transportFeeUsd) }}/month is added to the tuition invoice when a student is assigned to a bus.
    </p>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[560px] text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-2.5">Bus</th>
                    <th class="px-4 py-2.5">Plate</th>
                    <th class="px-4 py-2.5">Driver</th>
                    <th class="px-4 py-2.5">Seats</th>
                    <th class="px-4 py-2.5 text-right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($buses as $bus)
                    <tr class="border-b border-slate-50">
                        <td class="px-4 py-2.5">
                            <div class="font-medium text-slate-900">{{ $bus->name }}</div>
                            <x-status-badge :status="$bus->status->value" :label="$bus->status->label()" />
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $bus->vehicle?->plate_number ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $bus->vehicle?->driver?->full_name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $bus->active_assignments_count }} / {{ $bus->capacity() }}</td>
                        <td class="px-4 py-2.5 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('transport.buses.show', $bus) }}" class="text-xs font-medium text-blue-700 hover:underline">Open</a>
                            <a href="{{ route('transport.buses.edit', $bus) }}" class="text-xs font-medium text-slate-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">
                            No buses yet.
                            <a href="{{ route('transport.buses.create') }}" class="font-medium text-blue-700 hover:underline">Register a bus</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

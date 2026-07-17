@extends('layouts.app')

@section('title', ($route ? 'Edit bus' : 'Register bus').' — Dugsi ERP')

@section('content')
@php
    $vehicle = $route?->vehicle;
    $statusValue = old('status', $route?->status?->value ?? 'active');
    $driverId = old('driver_staff_id', $selectedDriverId ?? $vehicle?->driver_staff_id);
@endphp

<div class="space-y-4">
    <x-section-header
        :title="$route ? 'Edit bus' : 'Register bus'"
        :sub="'Bus details and driver · '.$academicYear"
    >
        <x-slot:action>
            <x-btn variant="secondary" href="{{ route('transport.index') }}">Back</x-btn>
        </x-slot:action>
    </x-section-header>

    <form method="POST"
        action="{{ $route ? route('transport.buses.update', $route) : route('transport.buses.store') }}"
        class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-5"
        id="bus-form">
        @csrf
        @if ($route)
            @method('PUT')
        @endif

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Bus / route name</label>
            <input type="text" name="route_name" required maxlength="120"
                value="{{ old('route_name', $route?->name) }}"
                placeholder="e.g. Hargeisa North"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error('route_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Plate number</label>
                <input type="text" name="plate_number" required maxlength="32"
                    value="{{ old('plate_number', $vehicle?->plate_number) }}"
                    placeholder="SL-BUS-01"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono">
                @error('plate_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Seat capacity</label>
                <input type="number" name="capacity" required min="1" max="200"
                    value="{{ old('capacity', $vehicle?->capacity ?? 30) }}"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('capacity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <div class="mb-1 flex items-center justify-between gap-2">
                <label class="block text-xs font-medium text-slate-700">Driver</label>
                <button type="button" id="toggle-add-driver" class="text-xs font-medium text-blue-700 hover:underline">
                    + Add driver
                </button>
            </div>
            <select name="driver_staff_id" id="driver-select" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">— Select driver —</option>
                @foreach ($drivers as $d)
                    <option value="{{ $d->id }}" @selected((string) $driverId === (string) $d->id)>
                        {{ $d->full_name }}@if ($d->phone) · {{ $d->phone }}@endif
                    </option>
                @endforeach
            </select>
            @error('driver_staff_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @if ($drivers->isEmpty())
                <p class="mt-1 text-xs text-slate-400">No drivers yet. Use <strong>Add driver</strong> to create one.</p>
            @endif
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Status</label>
            <select name="status" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="active" @selected($statusValue === 'active')>Active</option>
                <option value="inactive" @selected($statusValue === 'inactive')>Inactive</option>
            </select>
        </div>

        <button type="submit" class="rounded-md bg-dugsi-primary px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d56]">
            {{ $route ? 'Save bus' : 'Register bus' }}
        </button>
    </form>

    <div id="add-driver-panel" class="hidden max-w-xl rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
        <h3 class="mb-3 text-sm font-semibold text-slate-900">Add driver</h3>
        <form method="POST" action="{{ route('transport.drivers.store') }}" class="space-y-3" id="add-driver-form">
            @csrf
            <input type="hidden" name="return_to" value="{{ $route ? route('transport.buses.edit', $route) : route('transport.buses.create') }}">
            <input type="hidden" name="route_name" id="preserve-route-name" value="">
            <input type="hidden" name="plate_number" id="preserve-plate" value="">
            <input type="hidden" name="capacity" id="preserve-capacity" value="">
            <input type="hidden" name="status" id="preserve-status" value="">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Full name</label>
                <input type="text" name="full_name" required maxlength="255"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm bg-white"
                    placeholder="Driver name">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700">Phone (optional)</label>
                <input type="text" name="phone" maxlength="32"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm bg-white"
                    placeholder="+252…">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-dugsi-primary px-3 py-2 text-sm font-semibold text-white">Save driver</button>
                <button type="button" id="cancel-add-driver" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">Cancel</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const panel = document.getElementById('add-driver-panel');
    const openBtn = document.getElementById('toggle-add-driver');
    const cancelBtn = document.getElementById('cancel-add-driver');
    const busForm = document.getElementById('bus-form');
    const driverForm = document.getElementById('add-driver-form');

    function preserveBusFields() {
        if (!busForm) return;
        document.getElementById('preserve-route-name').value = busForm.querySelector('[name="route_name"]')?.value || '';
        document.getElementById('preserve-plate').value = busForm.querySelector('[name="plate_number"]')?.value || '';
        document.getElementById('preserve-capacity').value = busForm.querySelector('[name="capacity"]')?.value || '';
        document.getElementById('preserve-status').value = busForm.querySelector('[name="status"]')?.value || 'active';
    }

    openBtn?.addEventListener('click', () => {
        preserveBusFields();
        panel?.classList.remove('hidden');
    });
    cancelBtn?.addEventListener('click', () => panel?.classList.add('hidden'));
    // Copy latest bus fields right before save — opening the panel alone is not enough
    // if the admin keeps typing after "+ Add driver".
    driverForm?.addEventListener('submit', preserveBusFields);
})();
</script>
@endpush
@endsection

{{-- Shown on Overview (dashboard) when the logged-in user is linked to a staff record. --}}
@if (! empty($staffCheckinAction) && $staffCheckinAction !== 'done' && ! empty($staffCheckinUrl))
    <div class="flex w-full max-w-full gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 sm:w-fit">
        <span class="whitespace-nowrap rounded-md bg-white px-3 py-1.5 text-xs font-medium text-slate-900 shadow-sm">
            Overview
        </span>
        <a href="{{ $staffCheckinUrl }}"
            class="whitespace-nowrap rounded-md px-3 py-1.5 text-xs font-semibold transition-colors {{ $staffCheckinAction === 'check_out' ? 'bg-rose-600 text-white hover:bg-rose-700' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
            {{ $staffCheckinAction === 'check_out' ? 'Check out' : 'Check in' }}
        </a>
    </div>
@endif

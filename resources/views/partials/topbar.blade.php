@php $user = auth()->user(); @endphp

<header class="flex h-12 flex-shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-5">
    <div class="flex-1 text-sm font-medium text-slate-400">Qudus — Somaliland</div>
    <button type="button" class="relative p-1 text-slate-400 transition-colors hover:text-slate-700" aria-label="Notifications">
        <x-icon name="bell" :size="17" />
    </button>
    <div class="flex items-center gap-2">
        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-dugsi-primary text-[11px] font-bold text-white">
            {{ $user->initials() }}
        </div>
        <span class="text-[13px] font-medium text-slate-800">{{ $user->name }}</span>
    </div>
</header>

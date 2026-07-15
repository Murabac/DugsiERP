@php $user = auth()->user(); @endphp

<header class="flex h-12 flex-shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-3 sm:gap-3 sm:px-5">
    <button type="button" id="sidebar-open" class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 lg:hidden" aria-label="Open menu" aria-controls="app-sidebar" aria-expanded="false">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div class="min-w-0 flex-1 truncate text-sm font-medium text-slate-400">Qudus — Somaliland</div>
    <button type="button" class="relative hidden p-1 text-slate-400 transition-colors hover:text-slate-700 sm:inline-flex" aria-label="Notifications">
        <x-icon name="bell" :size="17" />
    </button>
    <div class="flex min-w-0 items-center gap-2">
        <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-dugsi-primary text-[11px] font-bold text-white">
            {{ $user->initials() }}
        </div>
        <span class="hidden max-w-[10rem] truncate text-[13px] font-medium text-slate-800 sm:inline">{{ $user->name }}</span>
    </div>
</header>

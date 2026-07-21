@php
    $user = auth()->user();
    $showSidebar = $showSidebar ?? ! request()->routeIs('modules.home');
    $schoolLabel = \App\Models\SchoolSetting::schoolName();
    $location = \App\Models\SchoolSetting::schoolLocation();
    if ($location) {
        $schoolLabel .= ' — '.$location;
    }
    $notifCount = 0;
    if ($user && $user->hasPermission('notifications.view')) {
        $notifCount = (int) \App\Models\NotificationLog::query()
            ->where('status', \App\Enums\NotificationStatus::Failed)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }
@endphp

<header @class([
    'flex h-12 flex-shrink-0 items-center gap-2 px-3 sm:gap-3 sm:px-5',
    'border-b border-slate-200 bg-white' => $showSidebar,
    'border-b border-white/10 bg-dugsi-primary' => ! $showSidebar,
])>
    @if ($showSidebar)
        <button type="button" id="sidebar-open" class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 lg:hidden" aria-label="Open menu" aria-controls="app-sidebar" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <a href="{{ route('modules.home') }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-dugsi-primary"
            title="Back to Apps">
            <x-icon name="arrow-left" :size="14" />
            <span>Apps</span>
        </a>
    @else
        <div class="flex items-center gap-2">
            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-white/15">
                <x-icon name="graduation-cap" :size="14" class="text-white" />
            </div>
            <span class="text-sm font-bold text-white">Dugsi ERP</span>
        </div>
    @endif
    <div @class([
        'min-w-0 flex-1 truncate text-sm font-medium',
        'text-slate-400' => $showSidebar,
        'text-white/55' => ! $showSidebar,
    ])>{{ $schoolLabel }}</div>
    @if ($user && $user->hasPermission('notifications.view'))
        <a href="{{ route('notifications.index', ['tab' => 'log']) }}"
            @class([
                'relative inline-flex p-1 transition-colors',
                'text-slate-400 hover:text-slate-700' => $showSidebar,
                'text-white/60 hover:text-white' => ! $showSidebar,
            ])
            aria-label="Notifications">
            <x-icon name="bell" :size="17" />
            @if ($notifCount > 0)
                <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                    {{ $notifCount > 9 ? '9+' : $notifCount }}
                </span>
            @endif
        </a>
    @else
        <button type="button"
            @class([
                'relative hidden p-1 transition-colors sm:inline-flex',
                'text-slate-400 hover:text-slate-700' => $showSidebar,
                'text-white/60 hover:text-white' => ! $showSidebar,
            ])
            aria-label="Notifications">
            <x-icon name="bell" :size="17" />
        </button>
    @endif
    <div class="relative" id="user-menu">
        <button type="button" id="user-menu-btn"
            @class([
                'flex min-w-0 items-center gap-2 rounded-md px-1.5 py-1 transition-colors',
                'hover:bg-slate-100' => $showSidebar,
                'hover:bg-white/10' => ! $showSidebar,
            ])
            aria-haspopup="menu"
            aria-expanded="false"
            aria-controls="user-menu-panel">
            <div @class([
                'flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                'bg-dugsi-primary text-white' => $showSidebar,
                'bg-white/20 text-white' => ! $showSidebar,
            ])>
                {{ $user->initials() }}
            </div>
            <span @class([
                'hidden max-w-[10rem] truncate text-[13px] font-medium sm:inline',
                'text-slate-800' => $showSidebar,
                'text-white' => ! $showSidebar,
            ])>{{ $user->name }}</span>
            <x-icon name="chevron-down" :size="14" @class([
                'hidden sm:inline',
                'text-slate-400' => $showSidebar,
                'text-white/50' => ! $showSidebar,
            ]) />
        </button>
        <div id="user-menu-panel"
            class="absolute right-0 z-50 mt-1 hidden w-52 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
            role="menu"
            hidden>
            <div class="border-b border-slate-100 px-3 py-2">
                <div class="truncate text-sm font-medium text-slate-900">{{ $user->name }}</div>
                <div class="truncate text-[11px] text-slate-500">{{ $user->email }}</div>
                <div class="mt-0.5 text-[11px] font-medium text-slate-400">{{ $user->roleLabel() }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" role="menuitem"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50">
                    <x-icon name="log-out" :size="14" class="text-slate-400" />
                    Sign out
                </button>
            </form>
        </div>
    </div>
</header>
<script>
(function () {
    const root = document.getElementById('user-menu');
    const btn = document.getElementById('user-menu-btn');
    const panel = document.getElementById('user-menu-panel');
    if (!root || !btn || !panel) return;

    function setOpen(open) {
        panel.classList.toggle('hidden', !open);
        panel.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        setOpen(panel.hidden);
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) setOpen(false);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') setOpen(false);
    });
})();
</script>

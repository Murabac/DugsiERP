@php
    use App\Support\Navigation;

    $user = auth()->user();
    $navItems = Navigation::for($user->role);
@endphp

<aside id="app-sidebar"
    class="fixed inset-y-0 left-0 z-50 flex h-full w-64 -translate-x-full flex-col overflow-hidden bg-dugsi-sidebar transition-transform duration-200 lg:static lg:z-auto lg:w-52 lg:translate-x-0 lg:flex-shrink-0">
    <div class="flex items-center justify-between border-b border-white/10 px-4 py-4">
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-500">
                <x-icon name="graduation-cap" :size="16" class="text-white" />
            </div>
            <div>
                <div class="text-sm font-bold leading-tight tracking-tight text-white">Dugsi ERP</div>
                <div class="text-[11px] text-blue-300/80">{{ $user->role->label() }}</div>
            </div>
        </div>
        <button type="button" id="sidebar-close" class="rounded-md p-1.5 text-blue-200/70 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
        @foreach ($navItems as $item)
            @if (($item['type'] ?? 'item') === 'group')
                @php
                    $groupActive = collect($item['children'])->contains(fn ($c) => request()->routeIs($c['route']));
                @endphp
                <details class="group/nav" @if ($groupActive || request()->routeIs('finance.*')) open @endif>
                    <summary class="flex w-full cursor-pointer list-none items-center gap-2.5 rounded-md px-3 py-2 text-left text-[13px] transition-colors {{ $groupActive ? 'font-medium text-white' : 'text-blue-100/60 hover:bg-white/8 hover:text-white' }}">
                        <x-icon :name="$item['icon']" :size="15" />
                        <span class="flex-1">{{ $item['label'] }}</span>
                        <x-icon name="chevron-down" :size="12" class="transition-transform group-open/nav:rotate-180" />
                    </summary>
                    <div class="mt-0.5 ml-3 space-y-0.5 border-l border-white/10 pl-3">
                        @foreach ($item['children'] as $child)
                            <a href="{{ route($child['route']) }}"
                                class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-[12px] transition-colors {{ request()->routeIs($child['route']) ? 'bg-blue-600 font-medium text-white' : 'text-blue-100/50 hover:bg-white/8 hover:text-white' }}">
                                <span class="h-1 w-1 flex-shrink-0 rounded-full {{ request()->routeIs($child['route']) ? 'bg-white' : 'bg-white/30' }}"></span>
                                {{ $child['label'] }}
                            </a>
                        @endforeach
                    </div>
                </details>
            @else
                <a href="{{ route($item['route']) }}"
                    class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-[13px] transition-colors {{ request()->routeIs($item['route']) ? 'bg-blue-600 font-medium text-white' : 'text-blue-100/60 hover:bg-white/8 hover:text-white' }}">
                    <x-icon :name="$item['icon']" :size="15" />
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-white/10 px-2 py-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-[13px] text-blue-100/50 transition-colors hover:bg-white/8 hover:text-white">
                <x-icon name="log-out" :size="15" />
                Sign Out
            </button>
        </form>
    </div>
</aside>

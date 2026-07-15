<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Dugsi ERP'))</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-screen overflow-hidden bg-dugsi-page text-slate-900 antialiased">
    <div class="flex h-full">
        {{-- Mobile overlay --}}
        <div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-black/40 lg:hidden" aria-hidden="true"></div>

        @include('partials.sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            @include('partials.topbar')
            <main class="flex-1 overflow-y-auto p-3 sm:p-5">
                @if ($errors->any())
                    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" data-dugsi-flash-error>
                        {{ $errors->first() }}
                        @if ($errors->count() > 1)
                            <span class="text-red-600">(+{{ $errors->count() - 1 }} more)</span>
                        @endif
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            const sidebar = document.getElementById('app-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const openBtn = document.getElementById('sidebar-open');
            const closeBtn = document.getElementById('sidebar-close');

            function setExpanded(open) {
                openBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
                overlay?.setAttribute('aria-hidden', open ? 'false' : 'true');
            }

            function openSidebar() {
                sidebar?.classList.remove('-translate-x-full');
                overlay?.classList.remove('hidden');
                setExpanded(true);
                closeBtn?.focus();
            }

            function closeSidebar() {
                sidebar?.classList.add('-translate-x-full');
                overlay?.classList.add('hidden');
                const wasOpen = openBtn?.getAttribute('aria-expanded') === 'true';
                setExpanded(false);
                if (wasOpen) openBtn?.focus();
            }

            setExpanded(false);
            openBtn?.addEventListener('click', openSidebar);
            closeBtn?.addEventListener('click', closeSidebar);
            overlay?.addEventListener('click', closeSidebar);
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeSidebar();
            });
            window.addEventListener('resize', () => {
                if (window.matchMedia('(min-width: 1024px)').matches) closeSidebar();
            });
        })();
    </script>
    @if (session('status'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.DugsiUI?.success(@json(session('status')));
            });
        </script>
    @endif
    @if (session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.DugsiUI?.error(@json(session('error')));
            });
        </script>
    @endif
    @stack('scripts')
</body>
</html>

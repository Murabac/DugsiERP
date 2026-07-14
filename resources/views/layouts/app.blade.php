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
        @include('partials.sidebar')
        <div class="flex min-w-0 flex-1 flex-col">
            @include('partials.topbar')
            <main class="flex-1 overflow-y-auto p-5">
                @if (session('status'))
                    <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                        {{ session('status') }}
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>

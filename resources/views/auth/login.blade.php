@extends('layouts.guest')

@section('title', 'Sign In — Dugsi ERP')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-dugsi-sidebar px-4">
    <div class="w-full max-w-sm">
        <div class="mb-5 text-center">
            <div class="mx-auto mb-2.5 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-500">
                <x-icon name="graduation-cap" :size="24" class="text-white" />
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Dugsi ERP</h1>
            <p class="mt-1 text-sm text-blue-300/70">School Management System</p>
            <p class="mt-0.5 text-xs text-blue-300/50">Qudus · Somaliland</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-2xl">
            <form method="POST" action="{{ route('login') }}" class="space-y-3">
                @csrf

                @if ($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div>
                    <label for="login" class="mb-1 block text-xs font-medium text-slate-600">Email / Phone</label>
                    <input
                        id="login"
                        name="login"
                        type="text"
                        value="{{ old('login', 'admin@dugsi.edu.sl') }}"
                        autocomplete="username"
                        required
                        autofocus
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-dugsi-primary"
                    >
                </div>

                <div>
                    <div class="mb-1 flex justify-between">
                        <label for="password" class="text-xs font-medium text-slate-600">Password</label>
                        <span class="cursor-default text-xs text-blue-700/60">Forgot password?</span>
                    </div>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-dugsi-primary"
                    >
                </div>

                <label class="flex items-center gap-2 text-xs text-slate-500">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-dugsi-primary focus:ring-dugsi-primary">
                    Remember me
                </label>

                <button
                    type="submit"
                    class="w-full rounded-md bg-dugsi-primary py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#162d56]"
                >
                    Sign In
                </button>
            </form>

            <div class="mt-4 rounded-md border border-slate-100 bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-500">
                <div class="mb-1 font-semibold text-slate-600">Demo accounts (password: <code>password</code>)</div>
                admin@dugsi.edu.sl · teacher@dugsi.edu.sl<br>
                finance@dugsi.edu.sl · superadmin@dugsi.edu.sl
            </div>
        </div>

        <p class="mt-4 text-center text-xs text-blue-300/40">© {{ date('Y') }} Dugsi ERP</p>
    </div>
</div>
@endsection

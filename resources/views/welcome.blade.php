<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Dugsi ERP') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-dugsi-page text-slate-900 antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-6">
        <div class="w-full max-w-lg rounded-lg border border-dugsi-border bg-dugsi-card p-8 shadow-sm">
            <div class="text-2xl font-semibold text-dugsi-primary tracking-tight">Dugsi ERP</div>
            <p class="mt-2 text-sm text-slate-500">
                School ERP for secondary school (Form 1–Form 4) · Somaliland · USD
            </p>
            <div class="mt-6 rounded-md bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-700">
                <div class="font-medium text-slate-900">Week 0 — project skeleton ready</div>
                <ul class="mt-2 space-y-1 text-slate-600 list-disc list-inside">
                    <li>Laravel + MySQL + Blade/Vite/Tailwind</li>
                    <li>Design tokens aligned with <code class="text-xs">/design-reference</code></li>
                    <li>Auth &amp; roles start in Week 1</li>
                </ul>
            </div>
            <p class="mt-4 text-xs text-slate-400">
                UI source of truth: <code>/design-reference</code> · Rules: <code>CONTEXT.md</code> (local)
            </p>
        </div>
    </div>
</body>
</html>

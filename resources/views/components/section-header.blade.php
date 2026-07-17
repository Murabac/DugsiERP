@props(['title', 'sub' => null])

<div {{ $attributes->merge(['class' => 'mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="min-w-0">
        <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
        @if ($sub)
            <p class="mt-0.5 text-xs text-slate-500">{{ $sub }}</p>
        @endif
    </div>
    @isset($action)
        <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
            {{ $action }}
        </div>
    @elseif ($slot->isNotEmpty())
        <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>

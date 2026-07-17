@props([
    'label',
    'value',
    'sub' => null,
    'icon' => null,
    'accent' => false,
])

<div {{ $attributes->merge([
    'class' => 'flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-4'.($accent ? ' border-l-[3px] border-l-dugsi-primary' : ''),
]) }}>
    @if ($icon)
        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-dugsi-primary">
            @if (is_string($icon))
                <x-icon :name="$icon" :size="17" />
            @else
                {{ $icon }}
            @endif
        </div>
    @endif
    <div class="min-w-0">
        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</div>
        <div class="mt-0.5 text-xl font-bold text-slate-900">{{ $value }}</div>
        @if ($sub)
            <div class="mt-0.5 text-xs text-slate-400">{{ $sub }}</div>
        @endif
    </div>
</div>

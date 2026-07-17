@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'readonly' => false,
    'error' => null,
])

@php
    $inputClasses = 'w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-900 transition focus:border-transparent focus:outline-none focus:ring-2 focus:ring-dugsi-primary '
        .($readonly ? 'bg-slate-50 text-slate-500' : 'bg-white');
    $err = $error ?? ($name ? $errors->first($name) : null);
@endphp

<div {{ $attributes->only('class')->merge(['class' => '']) }}>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="mb-1 block text-xs font-medium text-slate-700">
            {{ $label }}@if ($required)<span class="ml-0.5 text-red-500">*</span>@endif
        </label>
    @endif
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <input
            type="{{ $type }}"
            @if($name) name="{{ $name }}" id="{{ $name }}" @endif
            value="{{ $name ? old($name, $value) : $value }}"
            @if($required) required @endif
            @if($readonly) readonly @endif
            {{ $attributes->except('class')->merge(['class' => $inputClasses]) }}
        >
    @endif
    @if ($err)
        <p class="mt-1 text-xs text-red-600">{{ $err }}</p>
    @endif
</div>

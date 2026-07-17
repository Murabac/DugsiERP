@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
])

@php
    $selectClasses = 'w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-900 transition focus:border-transparent focus:outline-none focus:ring-2 focus:ring-dugsi-primary '
        .($disabled ? 'cursor-not-allowed bg-slate-50 text-slate-500' : 'bg-white');
    $err = $error ?? ($name ? $errors->first($name) : null);
@endphp

<div>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="mb-1 block text-xs font-medium text-slate-700">
            {{ $label }}@if ($required)<span class="ml-0.5 text-red-500">*</span>@endif
        </label>
    @endif
    <select
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->merge(['class' => $selectClasses]) }}
    >
        {{ $slot }}
    </select>
    @if ($err)
        <p class="mt-1 text-xs text-red-600">{{ $err }}</p>
    @endif
</div>

@props(['status', 'label' => null])

@php
    if ($label) {
        $display = $label;
        $key = is_object($status) && isset($status->value) ? $status->value : strtolower((string) $status);
    } elseif (is_object($status) && method_exists($status, 'label')) {
        $display = $status->label();
        $key = $status->value ?? strtolower($display);
    } else {
        $display = (string) $status;
        $key = strtolower((string) $status);
    }

    $classes = match ($key) {
        'active', 'primary', 'paid', 'present', 'sent', 'confirmed', 'success' => 'bg-green-100 text-green-800',
        'waitlisted', 'waiting', 'on_leave', 'partial', 'late', 'warning', 'queued' => 'bg-amber-100 text-amber-800',
        'teacher', 'info', 'graduated' => 'bg-blue-100 text-blue-800',
        'admin', 'finance' => 'bg-violet-100 text-violet-800',
        'super_admin', 'danger', 'unpaid', 'absent', 'failed', 'suspended', 'resigned' => 'bg-red-100 text-red-800',
        'librarian', 'neutral', 'stubbed', 'draft' => 'bg-slate-100 text-slate-600',
        'transferred' => 'bg-amber-100 text-amber-800',
        'archived' => 'bg-slate-100 text-slate-600',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $slot->isEmpty() ? $display : $slot }}
</span>

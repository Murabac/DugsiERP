@props([
    'name',
    'label' => 'Date',
    'value' => null,
    'default' => null,
    'minYear' => null,
    'maxYear' => null,
    'required' => false,
    'hint' => null,
    'allowEmpty' => false,
])

@php
    $minYear = $minYear ?? (now()->year - 40);
    $maxYear = $maxYear ?? now()->year;
    $default = $default ?? ($allowEmpty ? null : now()->toDateString());

    $raw = old($name, $value ?? $default);
    $isEmpty = $allowEmpty && ($raw === null || $raw === '');

    if ($isEmpty) {
        $year = null;
        $month = null;
        $day = null;
        $hiddenValue = '';
    } else {
        $fallback = $default ?? now()->toDateString();
        $parts = $raw ? explode('-', (string) $raw) : explode('-', $fallback);
        $year = (int) ($parts[0] ?? now()->year);
        $month = (int) ($parts[1] ?? now()->month);
        $day = (int) ($parts[2] ?? now()->day);
        $year = max($minYear, min($maxYear, $year));
        $hiddenValue = sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
    $uid = 'date_'.uniqid();
@endphp

<div {{ $attributes->class('col-span-2') }} data-date-select data-uid="{{ $uid }}" data-allow-empty="{{ $allowEmpty ? '1' : '0' }}">
    <label class="mb-1 block text-xs font-medium text-slate-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>

    <input
        type="hidden"
        name="{{ $name }}"
        id="{{ $uid }}_value"
        value="{{ $hiddenValue }}"
        @if ($required) required @endif
    >

    <div class="grid grid-cols-3 gap-2">
        <div>
            <label for="{{ $uid }}_day" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Day</label>
            <select id="{{ $uid }}_day" data-date-day
                class="w-full rounded-md border border-slate-300 bg-white px-2.5 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                @if ($allowEmpty)
                    <option value="" @selected($isEmpty)>—</option>
                @endif
                @for ($d = 1; $d <= 31; $d++)
                    <option value="{{ $d }}" @selected(! $isEmpty && $day === $d)>{{ $d }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label for="{{ $uid }}_month" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Month</label>
            <select id="{{ $uid }}_month" data-date-month
                class="w-full rounded-md border border-slate-300 bg-white px-2.5 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                @if ($allowEmpty)
                    <option value="" @selected($isEmpty)>—</option>
                @endif
                @foreach ($months as $num => $monthLabel)
                    <option value="{{ $num }}" @selected(! $isEmpty && $month === $num)>{{ $monthLabel }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="{{ $uid }}_year" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Year</label>
            <select id="{{ $uid }}_year" data-date-year
                class="w-full rounded-md border border-slate-300 bg-white px-2.5 py-2 text-sm font-medium focus:border-transparent focus:outline-none focus:ring-2 focus:ring-dugsi-primary">
                @if ($allowEmpty)
                    <option value="" @selected($isEmpty)>—</option>
                @endif
                @for ($y = $maxYear; $y >= $minYear; $y--)
                    <option value="{{ $y }}" @selected(! $isEmpty && $year === $y)>{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    <p class="mt-1.5 text-[11px] text-slate-400">{{ $hint ?? 'Day · Month · Year' }}</p>
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

@once
<script>
(() => {
    function daysInMonth(year, month) {
        return new Date(year, month, 0).getDate();
    }

    function wireDateSelect(root) {
        if (root.dataset.dateWired === '1') return;

        const dayEl = root.querySelector('[data-date-day]');
        const monthEl = root.querySelector('[data-date-month]');
        const yearEl = root.querySelector('[data-date-year]');
        const hidden = root.querySelector('input[type="hidden"]');
        const allowEmpty = root.dataset.allowEmpty === '1';
        if (!dayEl || !monthEl || !yearEl || !hidden) return;

        function rebuildDays(year, month, selectedDay) {
            const maxDay = daysInMonth(year, month);
            const keepEmpty = allowEmpty;
            const previous = selectedDay === '' || selectedDay === null || selectedDay === undefined
                ? ''
                : String(selectedDay);

            dayEl.innerHTML = '';
            if (keepEmpty) {
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = '—';
                dayEl.appendChild(empty);
            }
            for (let d = 1; d <= maxDay; d++) {
                const opt = document.createElement('option');
                opt.value = String(d);
                opt.textContent = String(d);
                dayEl.appendChild(opt);
            }

            if (previous === '') {
                dayEl.value = keepEmpty ? '' : '1';
            } else {
                dayEl.value = String(Math.min(Number(previous), maxDay));
            }
        }

        function sync() {
            const yRaw = yearEl.value;
            const mRaw = monthEl.value;
            const dRaw = dayEl.value;

            if (allowEmpty && (yRaw === '' || mRaw === '' || dRaw === '')) {
                hidden.value = '';
                return;
            }

            const y = Number(yRaw);
            const m = Number(mRaw);
            if (!y || !m) {
                hidden.value = '';
                return;
            }

            rebuildDays(y, m, dRaw);
            if (!dayEl.value) {
                hidden.value = '';
                return;
            }

            hidden.value = `${y}-${String(m).padStart(2, '0')}-${String(dayEl.value).padStart(2, '0')}`;
        }

        [dayEl, monthEl, yearEl].forEach(el => el.addEventListener('change', sync));
        root.closest('form')?.addEventListener('submit', sync);
        sync();
        root.dataset.dateWired = '1';
    }

    function wireAllDateSelects(root = document) {
        (root?.querySelectorAll?.('[data-date-select]') || []).forEach(wireDateSelect);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => wireAllDateSelects());
    } else {
        wireAllDateSelects();
    }

    window.addEventListener('dugsi:wire-date-selects', (event) => {
        wireAllDateSelects(event.detail?.root || document);
    });
})();
</script>
@endonce

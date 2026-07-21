@php
    /** @var list<string> $phoneValues */
    $phoneValues = old('phones', $phoneValues ?? ['']);
    if (! is_array($phoneValues) || $phoneValues === []) {
        $phoneValues = [''];
    }
@endphp
<div class="{{ $colSpan ?? 'col-span-2' }}" data-staff-phones>
    <div class="mb-1 flex items-center justify-between gap-2">
        <label class="block text-xs font-medium text-slate-700">Phone numbers</label>
        <button type="button" data-staff-phone-add
            class="text-[11px] font-medium text-blue-700 hover:underline">+ Add phone</button>
    </div>
    <div class="space-y-2" data-staff-phone-list>
        @foreach ($phoneValues as $i => $phoneValue)
            <div class="flex gap-2" data-staff-phone-row>
                <input name="phones[]" value="{{ $phoneValue }}" placeholder="+252 63 xxx xxxx"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <button type="button" data-staff-phone-remove title="Remove"
                    class="rounded-md border border-slate-300 px-2.5 text-sm text-slate-500 hover:bg-slate-50 {{ $i === 0 && count($phoneValues) === 1 ? 'invisible' : '' }}">×</button>
            </div>
        @endforeach
    </div>
    <p class="mt-1 text-[11px] text-slate-500">First number is primary (used for login phone if not set separately). Up to 5.</p>
    @error('phones')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    @error('phones.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

@once
@push('scripts')
<script>
(() => {
    const syncPhoneRemoveButtons = (root) => {
        const rows = root.querySelectorAll('[data-staff-phone-row]');
        rows.forEach((row) => {
            const btn = row.querySelector('[data-staff-phone-remove]');
            if (btn) btn.classList.toggle('invisible', rows.length <= 1);
        });
    };

    const wireStaffPhones = (root) => {
        if (!root || root.dataset.phonesWired === '1') return;
        root.dataset.phonesWired = '1';
        const list = root.querySelector('[data-staff-phone-list]');
        root.querySelector('[data-staff-phone-add]')?.addEventListener('click', () => {
            if (!list || list.children.length >= 5) return;
            const row = list.querySelector('[data-staff-phone-row]')?.cloneNode(true);
            if (!row) return;
            const input = row.querySelector('input');
            if (input) input.value = '';
            list.appendChild(row);
            syncPhoneRemoveButtons(root);
        });
        root.addEventListener('click', (event) => {
            const btn = event.target.closest('[data-staff-phone-remove]');
            if (!btn || !root.contains(btn)) return;
            const rows = list?.querySelectorAll('[data-staff-phone-row]');
            if (!rows || rows.length <= 1) return;
            btn.closest('[data-staff-phone-row]')?.remove();
            syncPhoneRemoveButtons(root);
        });
        syncPhoneRemoveButtons(root);
    };

    document.querySelectorAll('[data-staff-phones]').forEach(wireStaffPhones);
})();
</script>
@endpush
@endonce

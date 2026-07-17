@extends('layouts.app')

@section('title', 'Notifications — Dugsi ERP')

@section('content')
<div class="space-y-4">
    <x-section-header title="Notifications" :sub="'SMS templates and delivery log · '.$gatewayLabel">
        <x-slot:action>
            <span class="text-xs {{ $gatewayConfigured ? 'text-green-700' : 'text-amber-700' }}">
                {{ $gatewayConfigured ? $gatewayLabel.' configured' : 'Credentials not set — sends log as Failed' }}
            </span>
        </x-slot:action>
    </x-section-header>

    <x-tabs :active="$tab" :tabs="[
        ['key' => 'templates', 'label' => 'Templates', 'href' => route('notifications.index', ['tab' => 'templates'])],
        ['key' => 'log', 'label' => 'Log', 'href' => route('notifications.index', ['tab' => 'log'])],
    ]" />

    @if ($tab === 'templates')
        <div class="space-y-3">
            @foreach ($templates as $template)
                <div class="rounded-lg border border-slate-200 bg-white p-4" data-template-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-dugsi-primary">
                                <x-icon name="message-square" :size="17" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">{{ $template->name }}</h3>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    Trigger: {{ $template->type->label() }}
                                    · Updated {{ $template->updated_at?->format('j M Y') ?? '—' }}
                                </p>
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach ($template->variables ?? [] as $v)
                                        <code class="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] text-blue-800">{{ '{'.$v.'}' }}</code>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($template->is_active)
                                <x-status-badge status="success" label="Active" />
                            @else
                                <x-status-badge status="neutral" label="Inactive" />
                            @endif
                            <button type="button" class="text-xs font-medium text-blue-700 hover:underline" data-edit-toggle>Edit</button>
                        </div>
                    </div>
                    <div class="mt-3 rounded-md border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-700" data-body-view>
                        {{ $template->body }}
                    </div>
                    <form method="POST" action="{{ route('notifications.templates.update', $template) }}" class="mt-3 hidden space-y-3" data-body-edit>
                        @csrf
                        <textarea name="body" rows="3" required maxlength="1000"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-dugsi-primary">{{ old('body', $template->body) }}</textarea>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active)) class="rounded border-slate-300">
                            Active
                        </label>
                        <div class="flex gap-2">
                            <x-btn type="submit" size="sm">Save template</x-btn>
                            <button type="button" class="text-xs text-slate-500 hover:underline" data-edit-cancel>Cancel</button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 space-y-3">
            <h3 class="text-sm font-semibold text-slate-900">Send fee notice</h3>
            <p class="text-xs text-slate-500">Uses the templates above and the primary guardian phone.</p>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Month</th>
                            <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Balance</th>
                            <th class="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-500">Send</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unpaidInvoices as $invoice)
                            @php $bal = max(0, (float) $invoice->amount_due - (float) $invoice->amount_paid); @endphp
                            <tr class="border-b border-slate-50">
                                <td class="px-3 py-2">{{ $invoice->student?->full_name ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $invoice->billing_month->format('M Y') }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ \App\Support\Money::format($bal) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <form method="POST" action="{{ route('notifications.fee-reminder') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                        <input type="hidden" name="kind" value="reminder">
                                        <button type="submit" class="text-xs font-medium text-blue-700 hover:underline">Reminder</button>
                                    </form>
                                    <span class="text-slate-300">·</span>
                                    <form method="POST" action="{{ route('notifications.fee-reminder') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                        <input type="hidden" name="kind" value="overdue">
                                        <button type="submit" class="text-xs font-medium text-amber-700 hover:underline">Overdue</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-400">No open invoices.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            document.querySelectorAll('[data-template-card]').forEach((card) => {
                const view = card.querySelector('[data-body-view]');
                const edit = card.querySelector('[data-body-edit]');
                card.querySelector('[data-edit-toggle]')?.addEventListener('click', () => {
                    view.classList.add('hidden');
                    edit.classList.remove('hidden');
                });
                card.querySelector('[data-edit-cancel]')?.addEventListener('click', () => {
                    edit.classList.add('hidden');
                    view.classList.remove('hidden');
                });
            });
        </script>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-stat-card label="Sent" :value="(string) $stats['sent']" icon="check-circle" accent />
            <x-stat-card label="Failed" :value="(string) $stats['failed']" icon="bell" />
            <x-stat-card label="Delivery rate" :value="$stats['delivery_rate'].'%'" icon="bar-chart" />
        </div>

        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700">Delivery Log</h3>
                <form method="GET" action="{{ route('notifications.index') }}" class="flex flex-wrap items-end gap-2">
                    <input type="hidden" name="tab" value="log">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…"
                        class="w-40 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                    <select name="type" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                        <option value="">All types</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected($typeFilter === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <x-btn type="submit" variant="secondary" size="sm">Filter</x-btn>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">ID</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Type</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Student</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Recipient</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Date</th>
                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b border-slate-50 align-top">
                                <td class="px-4 py-2.5 text-slate-500">#{{ $log->id }}</td>
                                <td class="px-4 py-2.5"><x-status-badge status="info" :label="$log->type->label()" /></td>
                                <td class="px-4 py-2.5">{{ $log->student?->full_name ?? '—' }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs">{{ $log->recipient_phone ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $log->created_at?->format('j M Y H:i') }}</td>
                                <td class="px-4 py-2.5">
                                    <x-status-badge :status="$log->status" />
                                    @if ($log->error)
                                        <div class="mt-1 max-w-xs text-[11px] text-slate-500">{{ $log->error }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">No notifications yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

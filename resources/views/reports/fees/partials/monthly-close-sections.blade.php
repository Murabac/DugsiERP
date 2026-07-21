@php
    $isPrint = ! empty($print);
@endphp

{{-- Section 1 --}}
<div @class(['overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm' => ! $isPrint])>
    @if ($isPrint)
        <div style="background:#1e3a6e;color:#fff;text-align:center;padding:8px 10px;font-weight:600;font-size:13px;margin-bottom:0;border-radius:4px 4px 0 0;">{{ $labels['section_1'] }}</div>
    @else
        <div class="bg-[#1e3a6e] px-4 py-2.5 text-center text-sm font-semibold text-white">{{ $labels['section_1'] }}</div>
    @endif
    <div @class(['overflow-x-auto' => ! $isPrint])>
        <table @class([$isPrint ? 'data' : 'w-full min-w-[640px] text-sm']) @if($isPrint) style="border-top:0;border-radius:0 0 4px 4px;margin-bottom:12px;" @endif>
            <thead>
                <tr @class(['border-b border-slate-200 bg-slate-50' => ! $isPrint])>
                    <th @class([$isPrint ? '' : 'px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:28%" @endif>{{ $labels['class'] }}</th>
                    <th @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:18%" @endif>{{ $labels['total'] }}</th>
                    <th @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:18%" @endif>{{ $labels['paid'] }}</th>
                    <th @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:18%" @endif>{{ $labels['partial'] }}</th>
                    <th @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:18%" @endif>{{ $labels['unpaid'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($studentRows as $row)
                    <tr @class(['border-b border-slate-100' => ! $isPrint])>
                        <td @class([$isPrint ? '' : 'px-4 py-2.5 font-medium text-slate-900'])>{{ $row['label'] }}</td>
                        <td @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right tabular-nums'])>
                            {{ $row['total'] }}
                            <div @if($isPrint) style="font-size:11px;color:#64748b;" @else class="mt-0.5 text-xs text-slate-500" @endif>{{ \App\Support\Money::format($row['total_amount']) }}</div>
                        </td>
                        <td @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right tabular-nums text-green-700'])>
                            {{ $row['paid'] }}
                            <div @if($isPrint) style="font-size:11px;color:#15803d;" @else class="mt-0.5 text-xs" @endif>{{ \App\Support\Money::format($row['paid_amount']) }}</div>
                        </td>
                        <td @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right tabular-nums text-amber-700'])>
                            {{ $row['partial'] }}
                            <div @if($isPrint) style="font-size:11px;color:#b45309;" @else class="mt-0.5 text-xs" @endif>{{ \App\Support\Money::format($row['partial_amount']) }}</div>
                        </td>
                        <td @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right tabular-nums text-red-700'])>
                            {{ $row['unpaid'] }}
                            <div @if($isPrint) style="font-size:11px;color:#b91c1c;" @else class="mt-0.5 text-xs" @endif>{{ \App\Support\Money::format($row['unpaid_amount']) }}</div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr @class(['border-t-2 border-slate-300 bg-slate-50 font-bold' => ! $isPrint])>
                    <td @class([$isPrint ? '' : 'px-4 py-2.5 text-slate-900'])>{{ $labels['grand_total'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right tabular-nums'])>{{ $studentTotals['total'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right tabular-nums text-green-700'])>{{ $studentTotals['paid'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right tabular-nums text-amber-700'])>{{ $studentTotals['partial'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right tabular-nums text-red-700'])>{{ $studentTotals['unpaid'] }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@foreach ([
    ['section' => 'section_2', 'lines' => $incomeLines, 'total' => $incomeTotal, 'totalLabel' => $labels['income_total']],
    ['section' => 'section_3', 'lines' => $expenseLines, 'total' => $expenseTotal, 'totalLabel' => $labels['expense_total']],
] as $block)
<div @class(['overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm' => ! $isPrint])>
    @if ($isPrint)
        <div style="background:#1e3a6e;color:#fff;text-align:center;padding:8px 10px;font-weight:600;font-size:13px;margin-bottom:0;border-radius:4px 4px 0 0;">{{ $labels[$block['section']] }}</div>
    @else
        <div class="bg-[#1e3a6e] px-4 py-2.5 text-center text-sm font-semibold text-white">{{ $labels[$block['section']] }}</div>
    @endif
    <div @class(['overflow-x-auto' => ! $isPrint])>
        <table @class([$isPrint ? 'data' : 'w-full min-w-[480px] text-sm']) @if($isPrint) style="border-top:0;border-radius:0 0 4px 4px;margin-bottom:12px;" @endif>
            <thead>
                <tr @class(['border-b border-slate-200 bg-slate-50' => ! $isPrint])>
                    <th @class([$isPrint ? '' : 'px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:70%" @endif>{{ $labels['description'] }}</th>
                    <th @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:30%" @endif>{{ $labels['amount'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($block['lines'] as $line)
                    <tr @class(['border-b border-slate-100' => ! $isPrint])>
                        <td @class([$isPrint ? '' : 'px-4 py-2.5 font-medium text-slate-800'])>{{ $line['label'] }}</td>
                        <td @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right tabular-nums text-slate-900'])>{{ \App\Support\Money::format($line['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr @class(['bg-slate-100 font-semibold text-slate-900' => ! $isPrint])>
                    <td @class([$isPrint ? '' : 'px-4 py-3'])>{{ $block['totalLabel'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-3 text-right tabular-nums'])>{{ \App\Support\Money::format($block['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endforeach

{{-- Section 4 --}}
<div @class(['overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm' => ! $isPrint])>
    @if ($isPrint)
        <div style="background:#334155;color:#fff;text-align:center;padding:8px 10px;font-weight:600;font-size:13px;margin-bottom:0;border-radius:4px 4px 0 0;">{{ $labels['section_4'] }}</div>
    @else
        <div class="bg-slate-700 px-4 py-2.5 text-center text-sm font-semibold text-white">{{ $labels['section_4'] }}</div>
    @endif
    <div @class(['overflow-x-auto' => ! $isPrint])>
        <table @class([$isPrint ? 'data' : 'w-full min-w-[480px] text-sm']) @if($isPrint) style="border-top:0;border-radius:0 0 4px 4px;margin-bottom:12px;" @endif>
            <tbody>
                <tr @class(['border-b border-slate-100' => ! $isPrint])>
                    <td @class([$isPrint ? '' : 'px-4 py-3 font-medium text-slate-800'])>{{ $labels['income_total'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-3 text-right tabular-nums text-emerald-700'])>{{ \App\Support\Money::format($incomeTotal) }}</td>
                </tr>
                <tr @class(['border-b border-slate-100' => ! $isPrint])>
                    <td @class([$isPrint ? '' : 'px-4 py-3 font-medium text-slate-800'])>{{ $labels['expense_total'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-3 text-right tabular-nums text-red-700'])>− {{ \App\Support\Money::format($expenseTotal) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr @class(['bg-[#1e3a6e] font-bold text-white' => ! $isPrint])>
                    <td @class([$isPrint ? '' : 'px-4 py-3'])>{{ $labels['profit_loss'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-3 text-right tabular-nums'])>{{ \App\Support\Money::format($net) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Section 5 --}}
<div @class(['overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm' => ! $isPrint]) @if($isPrint) style="page-break-before:always;margin-top:12px;" @endif>
    @if ($isPrint)
        <div style="background:#1e3a6e;color:#fff;text-align:center;padding:8px 10px;font-weight:600;font-size:13px;margin-bottom:0;border-radius:4px 4px 0 0;">{{ $labels['section_5'] }}</div>
    @else
        <div class="bg-[#1e3a6e] px-4 py-2.5 text-center text-sm font-semibold text-white">{{ $labels['section_5'] }}</div>
    @endif
    <div @class(['overflow-x-auto' => ! $isPrint])>
        <table @class([$isPrint ? 'data' : 'w-full min-w-[480px] text-sm']) @if($isPrint) style="border-top:0;border-radius:0 0 4px 4px;margin-bottom:12px;" @endif>
            <thead>
                <tr @class(['border-b border-slate-200 bg-slate-50' => ! $isPrint])>
                    <th @class([$isPrint ? '' : 'px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:34%" @endif>{{ $labels['class'] }}</th>
                    <th @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:33%" @endif>{{ $labels['unpaid_students'] }}</th>
                    <th @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:33%" @endif>{{ $labels['missing_amount'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($unpaidRows as $row)
                    <tr @class(['border-b border-slate-100' => ! $isPrint])>
                        <td @class([$isPrint ? '' : 'px-4 py-3 font-medium text-slate-800'])>{{ $row['label'] }}</td>
                        <td @class([$isPrint ? 'num' : 'px-4 py-3 text-right tabular-nums'])>{{ $row['unpaid'] }}</td>
                        <td @class([$isPrint ? 'num' : 'px-4 py-3 text-right tabular-nums'])>{{ \App\Support\Money::format($row['unpaid_amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr @class(['bg-slate-100 font-semibold text-slate-900' => ! $isPrint])>
                    <td @class([$isPrint ? '' : 'px-4 py-3'])>{{ $labels['grand_total'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-3 text-right tabular-nums'])>{{ $unpaidTotals['unpaid'] }}</td>
                    <td @class([$isPrint ? 'num' : 'px-4 py-3 text-right tabular-nums'])>{{ \App\Support\Money::format($unpaidTotals['unpaid_amount']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Section 6 --}}
<div @class(['overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm' => ! $isPrint])>
    @if ($isPrint)
        <div style="background:#334155;color:#fff;text-align:center;padding:8px 10px;font-weight:600;font-size:13px;margin-bottom:0;border-radius:4px 4px 0 0;">{{ $labels['section_6'] }}</div>
    @else
        <div class="bg-slate-700 px-4 py-2.5 text-center text-sm font-semibold text-white">{{ $labels['section_6'] }}</div>
    @endif
    <div @class(['overflow-x-auto' => ! $isPrint])>
        <table @class([$isPrint ? 'data' : 'w-full min-w-[360px] text-sm']) @if($isPrint) style="border-top:0;border-radius:0 0 4px 4px;margin-bottom:12px;" @endif>
            <thead>
                <tr @class(['border-b border-slate-200 bg-slate-50' => ! $isPrint])>
                    <th @class([$isPrint ? '' : 'px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:70%" @endif>{{ $labels['description'] }}</th>
                    <th @class([$isPrint ? 'num' : 'px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-600']) @if($isPrint) style="width:30%" @endif>{{ $labels['overview_students'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($overview as $line)
                    <tr @class(['border-b border-slate-100' => ! $isPrint])>
                        <td @class([$isPrint ? '' : 'px-4 py-3 font-medium text-slate-800'])>{{ $line['label'] }}</td>
                        <td @class([$isPrint ? 'num' : 'px-4 py-3 text-right tabular-nums'])>{{ $line['students'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if ($isPrint)
<div class="sigs">
    <div class="sig">{{ $labels['sign_accountant'] }}</div>
    <div class="sig">{{ $labels['sign_manager'] }}</div>
    <div class="sig">{{ $labels['sign_approval'] }}</div>
</div>
<p style="margin-top:12px;font-size:11px;color:#64748b;">{{ $labels['date'] }}: ____________________ · {{ $labels['sign_stamp'] }}: ____________________</p>
@else
<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div class="border-t border-slate-300 pt-3 text-center text-xs text-slate-500">{{ $labels['sign_accountant'] }}</div>
        <div class="border-t border-slate-300 pt-3 text-center text-xs text-slate-500">{{ $labels['sign_manager'] }}</div>
        <div class="border-t border-slate-300 pt-3 text-center text-xs text-slate-500">{{ $labels['sign_approval'] }}</div>
    </div>
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="text-xs text-slate-500">{{ $labels['date'] }}: ____________________</div>
        <div class="text-xs text-slate-500">{{ $labels['sign_stamp'] }}: ____________________</div>
    </div>
</div>
@endif

<p @class(['text-xs text-slate-500' => ! $isPrint]) @if($isPrint) style="margin-top:8px;font-size:11px;color:#64748b;" @endif>{{ $labels['note'] }}</p>

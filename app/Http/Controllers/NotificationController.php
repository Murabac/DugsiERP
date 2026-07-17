<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\NotificationType;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Student;
use App\Support\Money;
use App\Support\NotificationDispatcher;
use App\Support\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'templates');
        if (! in_array($tab, ['templates', 'log'], true)) {
            $tab = 'templates';
        }

        $templates = NotificationTemplate::query()->orderBy('id')->get();

        $typeFilter = $request->query('type');
        $logsQuery = NotificationLog::query()
            ->with('student')
            ->latest('id');

        if ($typeFilter && NotificationType::tryFrom($typeFilter)) {
            $logsQuery->where('type', $typeFilter);
        }

        $logs = $logsQuery->limit(150)->get();

        $sent = NotificationLog::query()->where('status', 'sent')->count();
        $failed = NotificationLog::query()->whereIn('status', ['failed', 'stubbed'])->count();
        $total = max(1, $sent + $failed);
        $deliveryRate = round(($sent / $total) * 100, 1);

        $unpaidInvoices = Invoice::query()
            ->with(['student.primaryGuardian'])
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->whereDate('billing_month', '<=', now()->startOfMonth()->toDateString())
            ->orderByDesc('billing_month')
            ->limit(40)
            ->get();

        return view('notifications.index', [
            'tab' => $tab,
            'templates' => $templates,
            'logs' => $logs,
            'types' => NotificationType::options(),
            'typeFilter' => $typeFilter,
            'stats' => [
                'sent' => $sent,
                'failed' => $failed,
                'delivery_rate' => $deliveryRate,
            ],
            'gatewayConfigured' => SmsGateway::isConfigured(),
            'gatewayLabel' => SmsGateway::label(),
            'unpaidInvoices' => $unpaidInvoices,
        ]);
    }

    public function updateTemplate(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->update([
            'body' => $data['body'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('notifications.index', ['tab' => 'templates'])
            ->with('status', $template->name.' template saved.');
    }

    public function sendFeeReminder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'kind' => ['required', Rule::in(['reminder', 'overdue'])],
        ]);

        $invoice = Invoice::query()->with(['student.primaryGuardian'])->findOrFail($data['invoice_id']);
        $student = $invoice->student;
        abort_unless($student instanceof Student, 404);

        $balance = Money::round((float) $invoice->amount_due - (float) $invoice->amount_paid);
        if ($balance <= 0) {
            return redirect()
                ->route('notifications.index', ['tab' => 'log'])
                ->withErrors(['invoice_id' => 'Invoice has no outstanding balance.']);
        }

        $type = $data['kind'] === 'overdue'
            ? \App\Enums\NotificationType::FeeOverdue
            : \App\Enums\NotificationType::FeeReminder;

        $alreadySentId = \App\Models\NotificationLog::query()
            ->where('related_invoice_id', $invoice->id)
            ->where('type', $type)
            ->where('status', \App\Enums\NotificationStatus::Sent)
            ->where('created_at', '>=', now()->subHours(NotificationDispatcher::FEE_NOTICE_COOLDOWN_HOURS))
            ->value('id');

        if ($data['kind'] === 'overdue') {
            $days = max(1, (int) $invoice->billing_month->copy()->endOfMonth()->diffInDays(now(), false));
            $log = NotificationDispatcher::sendFeeOverdue($student, $invoice, $days);
        } else {
            $log = NotificationDispatcher::sendFeeReminder($student, $invoice);
        }

        $reused = $alreadySentId && (int) $log->id === (int) $alreadySentId;
        $label = $log->status->label();
        $status = $reused
            ? 'Fee notice already sent for '.$student->full_name.' within the last '.NotificationDispatcher::FEE_NOTICE_COOLDOWN_HOURS.' hours.'
            : 'Fee notice for '.$student->full_name.': '.$label.'.';

        return redirect()
            ->route('notifications.index', ['tab' => 'log'])
            ->with('status', $status);
    }
}

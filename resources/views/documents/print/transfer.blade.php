<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Transfer Certificate — {{ $student->full_name }} — {{ $schoolName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Georgia, 'Times New Roman', serif; color: #0f172a; margin: 0; padding: 24px; background: #f8fafc; }
        .sheet { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; padding: 36px 40px; }
        .header { border-bottom: 2px solid #1e3a6e; padding-bottom: 12px; margin-bottom: 20px; }
        .brand { font-size: 20px; font-weight: 700; color: #1e3a6e; }
        .sub { font-size: 12px; color: #64748b; margin-top: 2px; }
        .title { margin-top: 18px; text-align: center; font-size: 18px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .body { font-size: 14px; line-height: 1.7; margin-top: 24px; }
        .meta { margin-top: 20px; display: grid; grid-template-columns: 160px 1fr; gap: 8px 12px; font-size: 13px; }
        .label { color: #64748b; }
        .stamp { margin-top: 48px; text-align: right; font-size: 12px; color: #64748b; }
        .stamp-box { display: inline-block; border: 1px dashed #94a3b8; padding: 28px 36px; }
        .footer { margin-top: 36px; text-align: center; font-size: 11px; color: #94a3b8; }
        .actions { max-width: 720px; margin: 0 auto 16px; display: flex; gap: 8px; }
        .actions button, .actions a { padding: 8px 14px; border-radius: 6px; border: 0; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn-primary { background: #1e3a6e; color: #fff; }
        .btn-secondary { background: #fff; color: #1e3a6e; border: 1px solid #cbd5e1 !important; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none !important; } .sheet { border: 0; } }
    </style>
</head>
<body>
@php
    $leaving = isset($meta['date_of_leaving']) ? \Illuminate\Support\Carbon::parse($meta['date_of_leaving']) : $document->generated_at;
    $enrolledSince = ! empty($meta['enrolled_since']) ? \Illuminate\Support\Carbon::parse($meta['enrolled_since']) : null;
@endphp
<div class="actions no-print">
    <button type="button" class="btn-primary" onclick="window.print()">Print / Save PDF</button>
    <a href="{{ route('documents.index', ['tab' => 'history']) }}" class="btn-secondary">Back</a>
</div>
<div class="sheet">
    <div class="header">
        <div class="brand">{{ $schoolName }}</div>
        <div class="sub">{{ $schoolLetterheadSub }}</div>
        <div class="title">Transfer Certificate</div>
    </div>
    <div class="body">
        This is to certify that <strong>{{ $student->full_name }}</strong>
        (Student ID {{ $student->student_code }}),
        child of <strong>{{ $student->primaryGuardian?->full_name ?? '—' }}</strong>,
        was a bona fide student of this school
        @if ($schoolClass) in <strong>{{ $schoolClass->displayName() }}</strong>@endif.
    </div>
    <div class="meta">
        <div class="label">Enrolled since</div>
        <div>{{ $enrolledSince?->format('j F Y') ?? '—' }}</div>
        <div class="label">Date of leaving</div>
        <div>{{ $leaving->format('j F Y') }}</div>
        <div class="label">Reason</div>
        <div>{{ $meta['reason'] ?? '—' }}</div>
        <div class="label">Conduct</div>
        <div>{{ $meta['conduct'] ?? '—' }}</div>
        <div class="label">Academic progress</div>
        <div>{{ $meta['academic_progress'] ?? '—' }}</div>
    </div>
    <div class="stamp">
        <div class="stamp-box">Principal’s stamp / signature<br><span style="display:block;margin-top:28px;">{{ $document->generated_at->format('j F Y') }}</span></div>
    </div>
    <div class="footer">{{ $document->document_number }} · Generated via Dugsi ERP</div>
</div>
@include('documents.print.partials.autoprint')
</body>
</html>

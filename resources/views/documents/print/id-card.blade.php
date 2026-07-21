<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student ID — {{ $student->full_name }} — {{ $schoolName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #e2e8f0; font-family: 'Segoe UI', Tahoma, sans-serif; color: #0f172a; }
        .actions { max-width: 420px; margin: 0 auto 16px; display: flex; gap: 8px; }
        .actions button, .actions a { padding: 8px 14px; border-radius: 6px; border: 0; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn-primary { background: #1e3a6e; color: #fff; }
        .btn-secondary { background: #fff; color: #1e3a6e; border: 1px solid #cbd5e1 !important; }
        .card {
            width: 360px; margin: 0 auto; border-radius: 14px; overflow: hidden;
            background: #fff; box-shadow: 0 12px 28px rgba(15,39,68,.18);
        }
        .band { background: linear-gradient(135deg, #0f2744, #1e3a6e); color: #fff; padding: 14px 16px; }
        .band .school { font-size: 13px; font-weight: 700; letter-spacing: .02em; }
        .band .sub { font-size: 10px; opacity: .8; margin-top: 2px; }
        .body { display: grid; grid-template-columns: 88px 1fr; gap: 14px; padding: 16px; }
        .photo {
            width: 88px; height: 108px; border-radius: 8px; background: #e2e8f0; display: flex;
            align-items: center; justify-content: center; font-size: 28px; font-weight: 700; color: #1e3a6e;
            overflow: hidden;
        }
        .photo img { width: 100%; height: 100%; object-fit: cover; }
        .name { font-size: 16px; font-weight: 700; }
        .meta { margin-top: 8px; font-size: 12px; color: #475569; display: grid; gap: 4px; }
        .meta span { color: #94a3b8; display: inline-block; width: 72px; }
        .foot {
            border-top: 1px solid #e2e8f0; padding: 10px 16px; display: flex; justify-content: space-between;
            align-items: center; font-size: 10px; color: #64748b;
        }
        .qr {
            width: 42px; height: 42px; border: 1px dashed #94a3b8; display: flex; align-items: center;
            justify-content: center; font-size: 9px; color: #94a3b8;
        }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none !important; } .card { box-shadow: none; } }
    </style>
</head>
<body>
<div class="actions no-print">
    <button type="button" class="btn-primary" onclick="window.print()">Print ID card</button>
    <a href="{{ route('documents.index', ['tab' => 'history']) }}" class="btn-secondary">Back</a>
    <a href="{{ route('modules.home') }}" class="btn-secondary">Apps</a>
</div>
<div class="card">
    <div class="band">
        <div class="school">{{ $schoolName }}</div>
        <div class="sub">{{ $schoolLetterheadSub }}</div>
    </div>
    <div class="body">
        <div class="photo">
            @if ($student->photoUrl())
                <img src="{{ $student->photoUrl() }}" alt="">
            @else
                {{ $student->initials() }}
            @endif
        </div>
        <div>
            <div class="name">{{ $student->full_name }}</div>
            <div class="meta">
                <div><span>Class</span>{{ $schoolClass?->displayName() ?? '—' }}</div>
                <div><span>ID</span>{{ $student->student_code }}</div>
                <div><span>Year</span>{{ $academicYear }}</div>
                <div><span>Valid</span>AY {{ $academicYear }}</div>
            </div>
        </div>
    </div>
    <div class="foot">
        <div>{{ $document->document_number }}</div>
        <div class="qr">QR</div>
    </div>
</div>
@include('documents.print.partials.autoprint')
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of Completion — {{ $student->full_name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Cormorant+Garamond:wght@500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #f1f5f9; color: #0f172a; }
        .actions { max-width: 900px; margin: 0 auto 16px; display: flex; gap: 8px; }
        .actions button, .actions a {
            padding: 8px 14px; border-radius: 6px; border: 0; cursor: pointer; font-size: 13px; text-decoration: none;
        }
        .btn-primary { background: #1e3a6e; color: #fff; }
        .btn-secondary { background: #fff; color: #1e3a6e; border: 1px solid #cbd5e1 !important; }
        .frame {
            max-width: 900px; margin: 0 auto; background: #fffaf0;
            border: 14px solid #1e3a6e; padding: 10px; position: relative;
            box-shadow: 0 10px 30px rgba(15, 39, 68, .12);
        }
        .inner {
            border: 2px solid #c4a35a; padding: 48px 56px; text-align: center; position: relative;
            background:
                radial-gradient(circle at center, rgba(30,58,110,.04), transparent 55%),
                linear-gradient(180deg, #fffef8, #f8f1e3);
            min-height: 560px;
        }
        .ornament { position: absolute; width: 48px; height: 48px; border: 2px solid #c4a35a; }
        .ornament.tl { top: 16px; left: 16px; border-right: 0; border-bottom: 0; }
        .ornament.tr { top: 16px; right: 16px; border-left: 0; border-bottom: 0; }
        .ornament.bl { bottom: 16px; left: 16px; border-right: 0; border-top: 0; }
        .ornament.br { bottom: 16px; right: 16px; border-left: 0; border-top: 0; }
        .watermark {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', Georgia, serif; font-size: 120px; color: rgba(30,58,110,.05);
            pointer-events: none; user-select: none;
        }
        .school { font-family: 'Cormorant Garamond', Georgia, serif; font-size: 22px; letter-spacing: .08em; text-transform: uppercase; color: #1e3a6e; }
        .sub { margin-top: 4px; font-size: 13px; color: #64748b; }
        .heading { margin-top: 36px; font-family: 'Playfair Display', Georgia, serif; font-size: 42px; color: #1e3a6e; }
        .lead { margin-top: 18px; font-size: 15px; color: #475569; }
        .name { margin: 18px 0; font-family: 'Playfair Display', Georgia, serif; font-size: 36px; color: #0f172a; border-bottom: 1px solid #c4a35a; display: inline-block; padding: 0 18px 6px; }
        .body { max-width: 560px; margin: 0 auto; font-size: 15px; line-height: 1.55; color: #334155; }
        .seal {
            width: 84px; height: 84px; margin: 28px auto 8px; border-radius: 50%;
            border: 3px double #c4a35a; display: flex; align-items: center; justify-content: center;
            font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #1e3a6e; font-weight: 700;
        }
        .sigs { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; margin-top: 40px; max-width: 520px; margin-left: auto; margin-right: auto; }
        .sig-line { border-top: 1px solid #94a3b8; margin-top: 40px; padding-top: 6px; font-size: 12px; color: #64748b; }
        .footer { margin-top: 28px; font-size: 11px; color: #94a3b8; }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .frame { box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="actions no-print">
    <button type="button" class="btn-primary" onclick="window.print()">Print / Save PDF</button>
    <a href="{{ route('documents.index', ['tab' => 'history']) }}" class="btn-secondary">Back</a>
    <a href="{{ route('modules.home') }}" class="btn-secondary">Apps</a>
</div>
<div class="frame">
    <div class="inner">
        <div class="ornament tl"></div><div class="ornament tr"></div>
        <div class="ornament bl"></div><div class="ornament br"></div>
        <div class="watermark">★</div>
        <div class="school">{{ $schoolName }}</div>
        <div class="sub">{{ $schoolLetterheadSub }}</div>
        <div class="heading">Certificate of Completion</div>
        <div class="lead">This is to certify that</div>
        <div class="name">{{ $student->full_name }}</div>
        <div class="body">
            has successfully completed the secondary school programme
            @if ($schoolClass)
                in <strong>{{ $schoolClass->displayName() }}</strong>
            @endif
            for the academic year <strong>{{ $academicYear }}</strong>
            and is hereby awarded this certificate.
        </div>
        <div class="seal">Official<br>Seal</div>
        <div class="sigs">
            <div><div class="sig-line">Head Teacher</div></div>
            <div><div class="sig-line">Date · {{ $document->generated_at->format('j F Y') }}</div></div>
        </div>
        <div class="footer">{{ $document->document_number }} · Generated via Dugsi ERP</div>
    </div>
</div>
@include('documents.print.partials.autoprint')
</body>
</html>

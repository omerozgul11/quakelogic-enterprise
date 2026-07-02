@php
    $org = $orgName ?: 'QuakeLogic';
@endphp
<!doctype html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#f4f5f8;font-family:Helvetica,Arial,sans-serif;color:#1f2433;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#262261;color:#fff;border-radius:10px 10px 0 0;padding:18px 22px;">
            <div style="color:#F26522;font-size:11px;font-weight:bold;letter-spacing:2px;">{{ strtoupper($org) }}</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 10px 10px;padding:22px;">
            <div style="font-size:14px;line-height:1.6;white-space:pre-line;">{{ $bodyText }}</div>
            <p style="margin:22px 0 0;font-size:12px;color:#6b7280;border-top:1px solid #eef0f5;padding-top:12px;">
                📎 The document is attached as a PDF.
            </p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Helvetica', Arial, sans-serif; font-size: 11px; color: #1e293b; padding: 28px 32px; }
    .brand { color: #c75d24; font-size: 20px; font-weight: bold; }
    .subtitle { font-size: 13px; color: #475569; margin-top: 2px; }
    .meta { font-size: 9px; color: #94a3b8; margin-top: 4px; }
    .rule { border-bottom: 2px solid #c75d24; margin: 10px 0 16px; }
    h2 { font-size: 13px; color: #1e293b; margin: 18px 0 8px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f1f5f9; color: #475569; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; text-align: left; padding: 6px 8px; border-bottom: 1px solid #cbd5e1; }
    td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10.5px; }
    .r { text-align: right; }
    .kpi-table td { border: 1px solid #e2e8f0; padding: 8px 10px; width: 16.66%; }
    .kpi-label { font-size: 8.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
    .kpi-value { font-size: 14px; font-weight: bold; color: #1e293b; margin-top: 2px; }
    .pos { color: #047857; font-weight: bold; }
    .muted { color: #64748b; }
    .footer { position: fixed; bottom: 12px; left: 32px; right: 32px; font-size: 8.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>
@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 0);
@endphp

<div class="brand">{{ $organization }}</div>
<div class="subtitle">Team Performance Report — {{ $periodLabel }}</div>
<div class="meta">Generated {{ $generatedAt->format('F j, Y \a\t g:i A') }} by {{ $generatedBy }}</div>
<div class="rule"></div>

<h2>Summary</h2>
<table class="kpi-table">
    <tr>
        <td><div class="kpi-label">Proposals Created</div><div class="kpi-value">{{ $totals['created'] }}</div></td>
        <td><div class="kpi-label">Proposals Submitted</div><div class="kpi-value">{{ $totals['submitted'] }}</div></td>
        <td><div class="kpi-label">Contracts Won</div><div class="kpi-value">{{ $totals['awarded'] }}</div></td>
        <td><div class="kpi-label">Submitted Value</div><div class="kpi-value">{{ $fmt($totals['submitted_value']) }}</div></td>
        <td><div class="kpi-label">Earnings</div><div class="kpi-value">{{ $fmt($totals['earnings']) }}</div></td>
        <td><div class="kpi-label">Open Pipeline</div><div class="kpi-value">{{ $fmt($totals['pipeline_value']) }}</div></td>
    </tr>
</table>

<h2>Performance by User</h2>
<table>
    <thead>
        <tr>
            <th>User</th>
            <th>Role</th>
            <th class="r">Created</th>
            <th class="r">Submitted</th>
            <th class="r">Won</th>
            <th class="r">Lost</th>
            <th class="r">Win Rate</th>
            <th class="r">Submitted $</th>
            <th class="r">Earnings</th>
            <th class="r">Pipeline $</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($team as $row)
            <tr>
                <td><strong>{{ $row['user'] }}</strong>@if (!$row['is_active']) <span class="muted">(inactive)</span>@endif</td>
                <td class="muted">{{ $row['role'] ?? '—' }}</td>
                <td class="r">{{ $row['created'] }}</td>
                <td class="r">{{ $row['submitted'] }}</td>
                <td class="r pos">{{ $row['awarded'] }}</td>
                <td class="r">{{ $row['lost'] }}</td>
                <td class="r">{{ $row['win_rate'] !== null ? $row['win_rate'] . '%' : '—' }}</td>
                <td class="r">{{ $fmt($row['submitted_value']) }}</td>
                <td class="r pos">{{ $fmt($row['earnings']) }}</td>
                <td class="r">{{ $fmt($row['pipeline_value']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>Current Proposals by Status</h2>
<table>
    <thead>
        <tr>
            <th>Status</th>
            <th class="r">Proposals</th>
            <th class="r">Total Value</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($statusBreakdown as $s)
            <tr>
                <td>{{ $s['label'] }}</td>
                <td class="r">{{ $s['count'] }}</td>
                <td class="r">{{ $s['value'] > 0 ? $fmt($s['value']) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">No proposals yet.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">{{ $organization }} — Team Performance Report — Confidential</div>
</body>
</html>

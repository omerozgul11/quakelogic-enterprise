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
    .pos { color: #047857; font-weight: bold; }
    .muted { color: #64748b; }
    .footer { position: fixed; bottom: 12px; left: 32px; right: 32px; font-size: 8.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>
@php
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $fmt = fn ($v) => '$' . number_format((float) $v, 0);
@endphp

<div class="brand">{{ $organization }}</div>
<div class="subtitle">Reports &amp; Analytics</div>
<div class="meta">Generated {{ $generatedAt->format('F j, Y \a\t g:i A') }} by {{ $generatedBy }}</div>
<div class="rule"></div>

<h2>Proposal Activity — Last 12 Months</h2>
<table>
    <thead>
        <tr>
            <th>Month</th>
            <th class="r">Proposals</th>
            <th class="r">Awarded</th>
            <th class="r">Proposal Value</th>
            <th class="r">Award Value</th>
        </tr>
    </thead>
    <tbody>
        @forelse (collect($proposalTrend)->take(12)->reverse() as $row)
            <tr>
                <td>{{ $monthNames[$row['month'] - 1] }} {{ $row['year'] }}</td>
                <td class="r">{{ $row['total'] }}</td>
                <td class="r pos">{{ $row['awarded'] }}</td>
                <td class="r">{{ $fmt($row['proposal_value'] ?? 0) }}</td>
                <td class="r">{{ $fmt($row['award_value'] ?? 0) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No data available.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Commission Trend</h2>
<table>
    <thead>
        <tr>
            <th>Period</th>
            <th class="r">Commissions</th>
            <th class="r">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse (collect($commissionTrend)->reverse() as $row)
            <tr>
                <td>{{ $row['period_month'] }}</td>
                <td class="r">{{ $row['count'] }}</td>
                <td class="r">{{ $fmt($row['total_commissions']) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">No commission data available.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Top Contracts by Value</h2>
<table>
    <thead>
        <tr>
            <th style="width: 24px;">#</th>
            <th>Contract</th>
            <th>Agency</th>
            <th class="r">Value</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($topOpportunities as $i => $opp)
            <tr>
                <td class="muted">{{ $i + 1 }}</td>
                <td>{{ \Illuminate\Support\Str::limit($opp['title'], 80) }}</td>
                <td class="muted">{{ \Illuminate\Support\Str::limit($opp['agency_name'] ?? '—', 50) }}</td>
                <td class="r pos">{{ $fmt($opp['estimated_value']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No contracts with a value yet.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">{{ $organization }} — Reports &amp; Analytics — Confidential</div>
</body>
</html>

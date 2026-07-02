@php
    /**
     * Shared, branded procurement document PDF (PR / RFQ / PO / Bill).
     * Rendered from a normalized array built by ProcurementDocumentService.
     * Navy #262261 / orange #F26522 to match the rest of the QuakeLogic brand.
     */
    $cur = $doc['currency'] ?: 'USD';
    $money = fn ($n) => $cur.' '.number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #1f2433; font-size: 12px; margin: 0; }
    .wrap { padding: 4px 2px; }
    .head { border-bottom: 3px solid #262261; padding-bottom: 12px; margin-bottom: 16px; }
    .head-table { width: 100%; }
    .brand { color: #F26522; font-size: 10px; font-weight: bold; letter-spacing: 2px; }
    .org { font-size: 16px; font-weight: bold; color: #262261; }
    .doc-kind { color: #262261; font-size: 20px; font-weight: bold; text-align: right; }
    .doc-number { color: #6b7280; font-size: 12px; text-align: right; }
    .logo { height: 34px; }
    .cols { width: 100%; margin-bottom: 14px; }
    .cols td { vertical-align: top; width: 50%; padding-right: 14px; }
    .label { color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px; }
    .party-name { font-weight: bold; font-size: 13px; }
    .muted { color: #4b5563; }
    .meta-row td { padding: 1px 0; }
    .meta-k { color: #6b7280; padding-right: 10px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.items th { background: #262261; color: #fff; text-align: left; padding: 7px 8px; font-size: 11px; }
    table.items th.r, table.items td.r { text-align: right; }
    table.items td { padding: 7px 8px; border-bottom: 1px solid #eef0f5; }
    .sku { color: #9ca3af; font-size: 10px; }
    table.totals { width: 42%; margin-left: 58%; margin-top: 12px; }
    table.totals td { padding: 3px 8px; }
    table.totals td.k { text-align: right; color: #6b7280; }
    table.totals td.v { text-align: right; width: 120px; }
    table.totals tr.grand td { border-top: 2px solid #262261; font-weight: bold; color: #262261; font-size: 13px; padding-top: 6px; }
    .block { margin-top: 18px; }
    .block .label { margin-bottom: 4px; }
    .notes { white-space: pre-line; font-size: 12px; }
    .foot { margin-top: 26px; color: #6b7280; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <table class="head-table"><tr>
            <td style="vertical-align:top;">
                @if(!empty($doc['org']['logo']))
                    <img class="logo" src="{{ $doc['org']['logo'] }}" alt="logo">
                @else
                    <div class="brand">{{ strtoupper($doc['org']['name']) }}</div>
                    <div class="org">{{ $doc['org']['name'] }}</div>
                @endif
            </td>
            <td style="vertical-align:top;">
                <div class="doc-kind">{{ $doc['kind_label'] }}</div>
                <div class="doc-number">{{ $doc['number'] }}</div>
            </td>
        </tr></table>
    </div>

    <table class="cols"><tr>
        <td>
            <div class="label">{{ $doc['party_label'] }}</div>
            <div class="party-name">{{ $doc['party']['name'] ?: '—' }}</div>
            @foreach(($doc['party']['lines'] ?? []) as $ln)
                <div class="muted">{{ $ln }}</div>
            @endforeach
        </td>
        <td>
            <table style="width:100%;"><tbody>
                @foreach($doc['meta'] as $m)
                    <tr class="meta-row"><td class="meta-k">{{ $m['label'] }}</td><td>{{ $m['value'] }}</td></tr>
                @endforeach
            </tbody></table>
        </td>
    </tr></table>

    <table class="items">
        <thead><tr>
            <th>Item</th>
            @if($doc['show_unit'])<th>Unit</th>@endif
            <th class="r">Qty</th>
            <th class="r">Unit cost</th>
            <th class="r">Line total</th>
        </tr></thead>
        <tbody>
        @forelse($doc['items'] as $it)
            <tr>
                <td>{{ $it['description'] ?: ($it['sku'] ?: 'Item') }}@if($it['sku'] && $it['description'])<br><span class="sku">{{ $it['sku'] }}</span>@endif</td>
                @if($doc['show_unit'])<td>{{ $it['unit'] ?: '—' }}</td>@endif
                <td class="r">{{ $qty($it['quantity']) }}</td>
                <td class="r">{{ $money($it['unit_cost']) }}</td>
                <td class="r">{{ $money($it['line_total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $doc['show_unit'] ? 5 : 4 }}" class="muted" style="padding:12px 8px;">No line items.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="totals"><tbody>
        @foreach($doc['totals'] as $t)
            <tr class="{{ !empty($t['grand']) ? 'grand' : '' }}"><td class="k">{{ $t['label'] }}</td><td class="v">{{ $money($t['value']) }}</td></tr>
        @endforeach
    </tbody></table>

    @if(!empty($doc['notes']))
        <div class="block">
            <div class="label">Notes</div>
            <div class="notes">{{ $doc['notes'] }}</div>
        </div>
    @endif
    @if(!empty($doc['terms']))
        <div class="block">
            <div class="label">Terms</div>
            <div class="notes">{{ $doc['terms'] }}</div>
        </div>
    @endif

    <div class="foot">
        {{ $doc['org']['name'] }} · {{ $doc['kind_label'] }} {{ $doc['number'] }}@if(!empty($doc['footer_note'])) · {{ $doc['footer_note'] }}@endif
    </div>
</div>
</body>
</html>

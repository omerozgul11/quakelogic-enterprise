@php
    $org = $po->organization?->name ?: 'QuakeLogic';
    $supplier = $po->supplier;
    $cur = $po->currency ?: 'USD';
    $money = fn ($n) => $cur.' '.number_format((float) $n, 2);
@endphp
<!doctype html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#f4f5f8;font-family:Helvetica,Arial,sans-serif;color:#1f2433;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#262261;color:#fff;border-radius:10px 10px 0 0;padding:18px 22px;">
            <div style="color:#F26522;font-size:11px;font-weight:bold;letter-spacing:2px;">{{ strtoupper($org) }}</div>
            <div style="font-size:20px;font-weight:bold;margin-top:2px;">Purchase Order {{ $po->number }}</div>
            <div style="font-size:12px;color:#c9cce0;">
                {{ $po->order_date ? $po->order_date->format('M j, Y') : now()->format('M j, Y') }}
                @if($po->expected_date) · Expected {{ $po->expected_date->format('M j, Y') }}@endif
            </div>
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 10px 10px;padding:22px;">
            <p style="margin:0 0 4px;font-size:12px;color:#6b7280;">Supplier</p>
            <p style="margin:0;font-weight:bold;">{{ $supplier?->name ?? '—' }}</p>
            @if($supplier?->address_line1)
                <p style="margin:2px 0 0;font-size:13px;color:#4b5563;">
                    {{ $supplier->address_line1 }}@if($supplier->city), {{ $supplier->city }}@endif @if($supplier->state){{ $supplier->state }}@endif {{ $supplier->postal_code }}
                </p>
            @endif

            <p style="margin:18px 0 8px;font-size:13px;">Please supply the following:</p>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tr style="background:#f3f4f8;color:#262261;text-align:left;">
                    <th style="padding:7px 8px;">Item</th>
                    <th style="padding:7px 8px;text-align:right;">Qty</th>
                    <th style="padding:7px 8px;text-align:right;">Unit cost</th>
                    <th style="padding:7px 8px;text-align:right;">Line total</th>
                </tr>
                @foreach($po->items as $item)
                    <tr style="border-bottom:1px solid #f0f1f6;">
                        <td style="padding:7px 8px;">{{ $item->description ?: $item->sku ?: 'Item' }}@if($item->sku && $item->description)<br><span style="color:#9ca3af;font-size:11px;">{{ $item->sku }}</span>@endif</td>
                        <td style="padding:7px 8px;text-align:right;">{{ rtrim(rtrim(number_format((float) $item->quantity_ordered, 3), '0'), '.') }}</td>
                        <td style="padding:7px 8px;text-align:right;">{{ $money($item->unit_cost) }}</td>
                        <td style="padding:7px 8px;text-align:right;">{{ $money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </table>

            <table style="width:100%;margin-top:12px;font-size:13px;">
                <tr><td style="text-align:right;color:#6b7280;padding:2px 8px;">Subtotal</td><td style="text-align:right;width:120px;padding:2px 8px;">{{ $money($po->subtotal) }}</td></tr>
                @if((float) $po->tax_amount > 0)<tr><td style="text-align:right;color:#6b7280;padding:2px 8px;">Tax ({{ rtrim(rtrim(number_format((float) $po->tax_rate, 2), '0'), '.') }}%)</td><td style="text-align:right;padding:2px 8px;">{{ $money($po->tax_amount) }}</td></tr>@endif
                @if((float) $po->shipping_amount > 0)<tr><td style="text-align:right;color:#6b7280;padding:2px 8px;">Shipping</td><td style="text-align:right;padding:2px 8px;">{{ $money($po->shipping_amount) }}</td></tr>@endif
                <tr><td style="text-align:right;font-weight:bold;padding:6px 8px;border-top:2px solid #262261;">Total</td><td style="text-align:right;font-weight:bold;padding:6px 8px;border-top:2px solid #262261;">{{ $money($po->total) }}</td></tr>
            </table>

            @if($po->notes)
                <p style="margin:16px 0 4px;font-size:12px;color:#6b7280;">Notes</p>
                <p style="margin:0;font-size:13px;white-space:pre-line;">{{ $po->notes }}</p>
            @endif

            <p style="margin:20px 0 0;font-size:13px;color:#4b5563;">
                Please confirm receipt and expected delivery. Reference <strong>{{ $po->number }}</strong> on your invoice and shipping documents.
            </p>
            <p style="margin:14px 0 0;font-size:13px;">Thank you,<br><strong>{{ $org }}</strong></p>
        </div>
    </div>
</body>
</html>

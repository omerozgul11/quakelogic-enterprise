@extends('vendor.layout')
@section('title', 'Dashboard')

@section('topbar-actions')
    <form method="POST" action="{{ route('vendor.logout') }}">
        @csrf
        <button type="submit" class="btn ghost sm">Sign out</button>
    </form>
@endsection

@php
    $money = fn ($m, $c) => ($c ?: 'USD').' '.number_format((float) $m, 2);
    $date = fn ($d) => $d ? $d->format('M j, Y') : '—';
@endphp

@section('content')
    <div style="margin-bottom:18px;">
        <div style="font-size:20px; font-weight:bold; color:var(--navy);">{{ $supplier->name }}</div>
        <div class="muted">Signed in as {{ $contact->name }}{{ $contact->email ? ' · '.$contact->email : '' }}</div>
    </div>

    <div class="card">
        <h2>Purchase Orders</h2>
        @if($orders->isEmpty())
            <div class="empty">No purchase orders yet.</div>
        @else
            <table>
                <thead><tr><th>Number</th><th>Date</th><th>Status</th><th class="right">Total</th><th></th></tr></thead>
                <tbody>
                @foreach($orders as $o)
                    <tr>
                        <td class="num">{{ $o->number }}</td>
                        <td class="muted">{{ $date($o->order_date) }}</td>
                        <td><span class="pill">{{ $o->status->label() }}</span></td>
                        <td class="right">{{ $money($o->total, $o->currency) }}</td>
                        <td class="right"><a class="btn sm" href="{{ route('vendor.pdf', ['type' => 'purchase-orders', 'id' => $o->id]) }}" target="_blank" rel="noopener">PDF</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Quotations / RFQs</h2>
        @if($quotations->isEmpty())
            <div class="empty">No quotation requests yet.</div>
        @else
            <table>
                <thead><tr><th>Number</th><th>Date</th><th>Status</th><th class="right">Total</th><th></th></tr></thead>
                <tbody>
                @foreach($quotations as $q)
                    <tr>
                        <td class="num">{{ $q->number }}</td>
                        <td class="muted">{{ $date($q->quote_date) }}</td>
                        <td><span class="pill">{{ $q->status->label() }}</span></td>
                        <td class="right">{{ $money($q->total, $q->currency) }}</td>
                        <td class="right"><a class="btn sm" href="{{ route('vendor.pdf', ['type' => 'quotations', 'id' => $q->id]) }}" target="_blank" rel="noopener">PDF</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Bills</h2>
        @if($bills->isEmpty())
            <div class="empty">No bills yet.</div>
        @else
            <table>
                <thead><tr><th>Number</th><th>Bill date</th><th>Due</th><th>Status</th><th class="right">Total</th><th></th></tr></thead>
                <tbody>
                @foreach($bills as $b)
                    <tr>
                        <td class="num">{{ $b->number }}</td>
                        <td class="muted">{{ $date($b->bill_date) }}</td>
                        <td class="muted">{{ $date($b->due_date) }}</td>
                        <td><span class="pill">{{ $b->payment_status->label() }}</span></td>
                        <td class="right">{{ $money($b->total, $b->currency) }}</td>
                        <td class="right"><a class="btn sm" href="{{ route('vendor.pdf', ['type' => 'bills', 'id' => $b->id]) }}" target="_blank" rel="noopener">PDF</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection

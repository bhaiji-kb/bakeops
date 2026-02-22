@extends('layouts.app')

@section('content')
@php($isEmbed = request()->boolean('embed'))
@php($settings = $businessSettings ?? [])
@php($gstEnabled = (bool) ($settings['gst_enabled'] ?? false))
@php($businessName = trim((string) ($settings['business_name'] ?? 'BakeOps Bakery')) ?: 'BakeOps Bakery')
@php($businessAddress = trim((string) ($settings['business_address'] ?? '')))
@php($businessPhone = trim((string) ($settings['business_phone'] ?? '')))
@php($businessLogoUrl = trim((string) ($settings['business_logo_url'] ?? '')))
@php($gstin = trim((string) ($settings['gstin'] ?? '')))
@php($subTotal = (float) ($sale->sub_total ?: $sale->items->sum('total')))
@php($balanceDue = (float) $sale->balance_amount > 0)
@php($preferredCustomerName = $sale->customer?->name ?: ($sale->customer_name_snapshot ?: 'Walk-in / Unknown'))
@php($preferredCustomerIdentifier = $sale->customer_identifier ?: ($sale->customer?->mobile ?? $sale->customer?->identifier ?? '-'))
@php($customerStructuredAddress = $sale->customer?->formattedAddress() ?: '')
@php($preferredCustomerAddress = $customerStructuredAddress !== '' ? $customerStructuredAddress : ($sale->customer?->address ?: ($sale->customer_address_snapshot ?: '-')))

@if(!$isEmbed)
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h2 class="mb-0">Invoice</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('pos.sales.index') }}" class="btn btn-sm btn-outline-secondary">Invoice History</a>
        <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary">Order Management</a>
        <a href="{{ route('pos.sales.invoice.pdf', ['sale' => $sale->id]) }}" class="btn btn-sm btn-success">Download PDF</a>
        <button type="button" class="btn btn-sm btn-dark" onclick="window.print()">Print</button>
    </div>
</div>

<div class="row g-2 mb-3 no-print">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Invoice No</div>
                <div class="fw-bold">{{ $sale->bill_number }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Order Source</div>
                <div class="fw-bold">{{ strtoupper($sale->order_source ?: 'outlet') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Payment</div>
                <div class="fw-bold">{{ strtoupper($sale->payment_mode) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Status</div>
                <div class="fw-bold {{ $balanceDue ? 'text-danger' : 'text-success' }}">{{ $balanceDue ? 'BALANCE DUE' : 'PAID' }}</div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm" id="invoice-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    @if($businessLogoUrl !== '')
                        <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }} Logo" style="width:42px;height:42px;object-fit:cover;border:1px solid #d6deee;border-radius:8px;">
                    @endif
                    <h4 class="mb-0">{{ $businessName }}</h4>
                </div>
                <div class="small text-muted">Tax Invoice / Customer Bill</div>
                @if($businessAddress !== '')
                    <div class="small text-muted">{{ $businessAddress }}</div>
                @endif
                @if($businessPhone !== '')
                    <div class="small text-muted">Phone: {{ $businessPhone }}</div>
                @endif
                @if($gstEnabled && $gstin !== '')
                    <div class="small text-muted">GSTIN: {{ $gstin }}</div>
                @endif
            </div>
            <div class="text-end">
                <div><strong>Invoice:</strong> {{ $sale->bill_number }}</div>
                <div><strong>Date:</strong> {{ $sale->created_at->format('Y-m-d H:i') }}</div>
                <div><strong>Payment:</strong> {{ strtoupper($sale->payment_mode) }}</div>
                <div><strong>Source:</strong> {{ strtoupper($sale->order_source ?: 'outlet') }}</div>
                @if($sale->order_reference)
                    <div><strong>Order Ref:</strong> {{ $sale->order_reference }}</div>
                @endif
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body py-2">
                        <div class="small text-muted mb-1">Customer</div>
                        <div><strong>{{ $preferredCustomerName }}</strong></div>
                        <div class="small text-muted">
                            <strong>Identifier:</strong> {{ $preferredCustomerIdentifier }}
                        </div>
                        <div class="small text-muted">
                            <strong>Address:</strong> {{ $preferredCustomerAddress }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body py-2">
                        <div class="small text-muted mb-1">Operator</div>
                        <div><strong>{{ $sale->createdBy->name ?? 'System' }}</strong></div>
                        <div class="small text-muted">
                            Role: {{ strtoupper($sale->createdBy->role ?? 'N/A') }}
                            @if($sale->createdBy?->email)
                                | {{ $sale->createdBy->email }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-sm table-bordered align-middle">
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 15%">Code</th>
                    <th style="width: 35%">Item</th>
                    <th style="width: 10%">Qty</th>
                    <th style="width: 15%">Rate</th>
                    <th style="width: 15%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->product->code ?? '-' }}</td>
                        <td>{{ $item->product->name ?? 'Unknown' }}</td>
                        <td>{{ number_format($item->quantity, 2) }}</td>
                        <td>Rs.{{ number_format($item->price, 2) }}</td>
                        <td>Rs.{{ number_format($item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="row mt-3">
            <div class="col-md-6 small text-muted">
                Thank you for your purchase. Keep this invoice for accounting and return references.
            </div>
            <div class="col-md-6">
                <table class="table table-sm mb-0">
                    <tr>
                        <th>Subtotal</th>
                        <td class="text-end">Rs.{{ number_format($subTotal, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Discount</th>
                        <td class="text-end">Rs.{{ number_format($sale->discount_amount, 2) }}</td>
                    </tr>
                    @if($gstEnabled)
                        <tr>
                            <th>Tax (GST)</th>
                            <td class="text-end">Rs.{{ number_format($sale->tax_amount, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th>Round Off</th>
                        <td class="text-end">Rs.{{ number_format($sale->round_off, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Total</th>
                        <td class="text-end"><strong>Rs.{{ number_format($sale->total_amount, 2) }}</strong></td>
                    </tr>
                    <tr>
                        <th>Paid</th>
                        <td class="text-end">Rs.{{ number_format($sale->paid_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Balance</th>
                        <td class="text-end {{ (float) $sale->balance_amount > 0 ? 'text-danger' : 'text-success' }}">
                            Rs.{{ number_format($sale->balance_amount, 2) }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
@if($isEmbed)
body {
    background: #fff !important;
}

.app-sidebar,
.app-topbar,
.shell-footer {
    display: none !important;
}

.app-shell {
    display: block !important;
    min-height: auto !important;
}

.app-main {
    display: block !important;
    width: 100% !important;
}

.app-main main {
    padding: 0 !important;
}

#invoice-card {
    border: 0 !important;
    box-shadow: none !important;
}
@endif

@media print {
    .no-print {
        display: none !important;
    }

    body {
        background: #fff;
    }

    #invoice-card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>
@endsection

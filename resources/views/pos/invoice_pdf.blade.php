<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $sale->bill_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
        }
        .wrap {
            border: 1px solid #d1d5db;
            padding: 16px;
        }
        .row {
            width: 100%;
            clear: both;
        }
        .left {
            float: left;
            width: 58%;
        }
        .right {
            float: right;
            width: 40%;
            text-align: right;
        }
        .muted {
            color: #6b7280;
            font-size: 11px;
        }
        .section {
            margin-top: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
        }
        th {
            background: #f3f4f6;
            text-align: left;
        }
        .totals td, .totals th {
            border: none;
            padding: 4px 2px;
        }
        .totals .label {
            text-align: right;
            width: 70%;
        }
        .totals .value {
            text-align: right;
            width: 30%;
        }
        .highlight {
            font-weight: bold;
            border-top: 1px solid #111827;
        }
        .footer {
            margin-top: 24px;
            font-size: 11px;
            color: #6b7280;
        }
    </style>
</head>
<body>
@php
    $settings = $businessSettings ?? [];
    $gstEnabled = (bool) ($settings['gst_enabled'] ?? false);
    $businessName = trim((string) ($settings['business_name'] ?? 'BakeOps Bakery')) ?: 'BakeOps Bakery';
    $businessAddress = trim((string) ($settings['business_address'] ?? ''));
    $businessPhone = trim((string) ($settings['business_phone'] ?? ''));
    $businessLogoPath = trim((string) ($settings['business_logo_path'] ?? ''));
    $gstin = trim((string) ($settings['gstin'] ?? ''));
    $subTotal = (float) ($sale->sub_total ?: $sale->items->sum('total'));
    $preferredCustomerName = $sale->customer?->name ?: ($sale->customer_name_snapshot ?: 'Walk-in / Unknown');
    $preferredCustomerIdentifier = $sale->customer_identifier ?: ($sale->customer?->mobile ?? $sale->customer?->identifier ?? '-');
    $customerStructuredAddress = $sale->customer?->formattedAddress() ?: '';
    $preferredCustomerAddress = $customerStructuredAddress !== '' ? $customerStructuredAddress : ($sale->customer?->address ?: ($sale->customer_address_snapshot ?: '-'));

    $businessLogoDataUri = '';
    if ($businessLogoPath !== '') {
        $logoAbsolutePath = storage_path('app/public/' . ltrim($businessLogoPath, '/'));
        if (is_file($logoAbsolutePath)) {
            $logoRaw = @file_get_contents($logoAbsolutePath);
            if ($logoRaw !== false) {
                $mimeType = @mime_content_type($logoAbsolutePath) ?: 'image/png';
                $businessLogoDataUri = 'data:' . $mimeType . ';base64,' . base64_encode($logoRaw);
            }
        }
    }
@endphp

<div class="wrap">
    <div class="row">
        <div class="left">
            @if($businessLogoDataUri !== '')
                <img src="{{ $businessLogoDataUri }}" alt="{{ $businessName }} Logo" style="width:44px;height:44px;object-fit:cover;border:1px solid #d1d5db;border-radius:8px;margin-bottom:4px;">
            @endif
            <h2 style="margin: 0 0 4px;">{{ $businessName }}</h2>
            <div class="muted">Bakery Billing System</div>
            <div class="muted">Invoice / Customer Bill</div>
            @if($businessAddress !== '')
                <div class="muted">{{ $businessAddress }}</div>
            @endif
            @if($businessPhone !== '')
                <div class="muted">Phone: {{ $businessPhone }}</div>
            @endif
            @if($gstEnabled && $gstin !== '')
                <div class="muted">GSTIN: {{ $gstin }}</div>
            @endif
        </div>
        <div class="right">
            <div><strong>Invoice:</strong> {{ $sale->bill_number }}</div>
            <div><strong>Date:</strong> {{ $sale->created_at->format('Y-m-d H:i') }}</div>
            <div><strong>Payment:</strong> {{ strtoupper($sale->payment_mode) }}</div>
            <div><strong>Source:</strong> {{ strtoupper($sale->order_source ?: 'outlet') }}</div>
            @if($sale->order_reference)
                <div><strong>Order Ref:</strong> {{ $sale->order_reference }}</div>
            @endif
        </div>
    </div>

    <div style="clear: both;"></div>

    <div class="section">
        <table>
            <tr>
                <th style="width: 18%;">Customer</th>
                <td style="width: 32%;">{{ $preferredCustomerName }}</td>
                <th style="width: 18%;">Identifier</th>
                <td style="width: 32%;">{{ $preferredCustomerIdentifier }}</td>
            </tr>
            <tr>
                <th>Address</th>
                <td colspan="3">{{ $preferredCustomerAddress }}</td>
            </tr>
            <tr>
                <th>Billed By</th>
                <td>{{ $sale->createdBy->name ?? 'System' }}</td>
                <th>User Role</th>
                <td>{{ strtoupper($sale->createdBy->role ?? 'N/A') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">#</th>
                    <th style="width: 40%;">Item</th>
                    <th style="width: 14%;">Qty</th>
                    <th style="width: 18%;">Rate</th>
                    <th style="width: 20%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->product->name ?? 'Unknown' }}</td>
                        <td>{{ number_format($item->quantity, 2) }}</td>
                        <td>Rs.{{ number_format($item->price, 2) }}</td>
                        <td>Rs.{{ number_format($item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <table class="totals">
            <tr>
                <th class="label">Subtotal</th>
                <td class="value">Rs.{{ number_format($subTotal, 2) }}</td>
            </tr>
            <tr>
                <th class="label">Discount</th>
                <td class="value">Rs.{{ number_format($sale->discount_amount, 2) }}</td>
            </tr>
            @if($gstEnabled)
                <tr>
                    <th class="label">Tax (GST)</th>
                    <td class="value">Rs.{{ number_format($sale->tax_amount, 2) }}</td>
                </tr>
            @endif
            <tr>
                <th class="label">Round Off</th>
                <td class="value">Rs.{{ number_format($sale->round_off, 2) }}</td>
            </tr>
            <tr class="highlight">
                <th class="label">Total</th>
                <td class="value">Rs.{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <th class="label">Paid</th>
                <td class="value">Rs.{{ number_format($sale->paid_amount, 2) }}</td>
            </tr>
            <tr>
                <th class="label">Balance</th>
                <td class="value">Rs.{{ number_format($sale->balance_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Thank you for your order. This is a system-generated invoice from BakeOps POS.
    </div>
</div>
</body>
</html>

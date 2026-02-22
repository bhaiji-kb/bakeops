<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOT {{ $order->kot?->kot_number ?: $order->order_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 18px; }
        .head { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .muted { color: #555; font-size: 12px; }
        .box { border: 1px solid #222; padding: 8px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; }
        th { background: #f2f2f2; }
        .text-end { text-align: right; }
        .print-btn { margin-bottom: 10px; }
        @media print {
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print KOT</button>

    <div class="head">
        <div>
            <h2 style="margin:0;">Kitchen Order Ticket</h2>
            <div class="muted">BakeOps Kitchen</div>
        </div>
        <div style="text-align:right;">
            <div><strong>KOT:</strong> {{ $order->kot?->kot_number ?: '-' }}</div>
            <div><strong>Order:</strong> {{ $order->order_number }}</div>
            <div><strong>Time:</strong> {{ $order->created_at?->format('Y-m-d H:i') }}</div>
        </div>
    </div>

    <div class="box">
        <div><strong>Source:</strong> {{ strtoupper($order->source) }}</div>
        <div><strong>Customer:</strong> {{ $order->customer_name ?: 'Walk-in / Unknown' }}</div>
        <div><strong>Mobile:</strong> {{ $order->customer_identifier ?: '-' }}</div>
        <div><strong>Address:</strong> {{ $order->customer_address ?: '-' }}</div>
        <div><strong>Order Notes:</strong> {{ $order->notes ?: '-' }}</div>
        <div><strong>Status:</strong> {{ strtoupper($order->status) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:8%;">#</th>
                <th>Item</th>
                <th style="width:20%;" class="text-end">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->item_name }}</td>
                    <td class="text-end">{{ number_format((float) $item->quantity, 3) }} {{ $item->unit ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="muted" style="margin-top: 10px;">
        Printed at: {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>

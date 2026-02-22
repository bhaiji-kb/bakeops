@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Online Orders Queue</h2>
    <div class="d-flex gap-2">
        <a
            href="{{ route('pos.online_orders.index', ['channel' => $channel, 'status' => $status, 'q' => $q]) }}"
            class="btn btn-sm btn-outline-dark {{ ($queueInteractive ?? false) ? '' : 'disabled' }}"
            {{ ($queueInteractive ?? false) ? '' : 'aria-disabled=true tabindex=-1' }}
        >Refresh</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">
        {{ session('success') }}
        @if(session('invoice_sale_id'))
            <button
                type="button"
                class="btn btn-link alert-link p-0 align-baseline js-invoice-preview"
                data-invoice-url="{{ route('pos.sales.invoice', ['sale' => session('invoice_sale_id'), 'embed' => 1]) }}"
                data-pdf-url="{{ route('pos.sales.invoice.pdf', ['sale' => session('invoice_sale_id')]) }}"
            >Open Invoice</button>
            |
            <a href="{{ route('pos.sales.invoice.pdf', ['sale' => session('invoice_sale_id')]) }}" class="alert-link">PDF</a>
        @endif
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if(!($queueInteractive ?? false))
    <div class="alert alert-warning py-2">
        No active connectors configured. Queue controls are muted. Configure connectors in Admin first.
    </div>
@else
    <div class="small text-muted mb-2">Channels are auto-loaded from active connector config.</div>
@endif

<form method="GET" action="{{ route('pos.online_orders.index') }}" class="row g-2 mb-3">
    <div class="col-md-2">
        <label class="form-label">Channel</label>
        <select name="channel" class="form-select" {{ ($queueInteractive ?? false) ? '' : 'disabled' }}>
            <option value="">All</option>
            @foreach($channels as $channelOption)
                <option value="{{ $channelOption }}" {{ $channel === $channelOption ? 'selected' : '' }}>
                    {{ strtoupper($channelOption) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" {{ ($queueInteractive ?? false) ? '' : 'disabled' }}>
            <option value="">All</option>
            @foreach($statuses as $statusOption)
                <option value="{{ $statusOption }}" {{ $status === $statusOption ? 'selected' : '' }}>
                    {{ strtoupper($statusOption) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-5">
        <label class="form-label">Search</label>
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Order ID / customer / mobile" {{ ($queueInteractive ?? false) ? '' : 'disabled' }}>
    </div>
    <div class="col-md-3 d-grid">
        <label class="form-label">&nbsp;</label>
        <button type="submit" class="btn btn-outline-dark" {{ ($queueInteractive ?? false) ? '' : 'disabled' }}>Apply Filters</button>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        @if($orders->isEmpty())
            <div class="p-3 text-muted">No online orders found.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Channel</th>
                            <th>External ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Connector</th>
                            <th>Sale</th>
                            <th>Order + KOT</th>
                            <th style="min-width: 260px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $statusLower = strtolower((string) $order->status);
                                $acceptDisabled = in_array($statusLower, ['accepted', 'ready', 'delivered', 'rejected', 'cancelled'], true);
                                $readyDisabled = in_array($statusLower, ['ready', 'delivered', 'rejected', 'cancelled'], true);
                                $rejectDisabled = in_array($statusLower, ['rejected', 'cancelled', 'delivered'], true);
                            @endphp
                            <tr>
                                <td>
                                    <div>{{ $order->last_event_at?->format('Y-m-d H:i') ?: '-' }}</div>
                                    <div class="small text-muted">Updated #{{ $order->id }}</div>
                                </td>
                                <td>{{ strtoupper($order->channel) }}</td>
                                <td>
                                    <div><strong>{{ $order->external_order_id }}</strong></div>
                                    @if($order->notes)
                                        <div class="small text-muted">{{ $order->notes }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $order->customer_name ?: '-' }}</div>
                                    <div class="small text-muted">{{ $order->customer_identifier ?: '-' }}</div>
                                </td>
                                <td>{{ $order->order_total !== null ? 'Rs.' . number_format((float) $order->order_total, 2) : '-' }}</td>
                                <td><span class="badge bg-secondary">{{ strtoupper($order->status) }}</span></td>
                                <td>{{ $order->connector?->name ?: '-' }}</td>
                                <td>
                                    @if($order->sale)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-dark js-invoice-preview"
                                            data-invoice-url="{{ route('pos.sales.invoice', ['sale' => $order->sale->id, 'embed' => 1]) }}"
                                            data-pdf-url="{{ route('pos.sales.invoice.pdf', ['sale' => $order->sale->id]) }}"
                                        >
                                            {{ $order->sale->bill_number }}
                                        </button>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($order->order)
                                        <a href="{{ route('orders.index', ['q' => $order->order->order_number]) }}" class="btn btn-sm btn-outline-secondary">
                                            {{ $order->order->order_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <form method="POST" action="{{ route('pos.online_orders.accept', ['channelOrder' => $order->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" {{ ($acceptDisabled || !($queueInteractive ?? false)) ? 'disabled' : '' }}>Accept</button>
                                        </form>
                                        <form method="POST" action="{{ route('pos.online_orders.ready', ['channelOrder' => $order->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary" {{ ($readyDisabled || !($queueInteractive ?? false)) ? 'disabled' : '' }}>Ready</button>
                                        </form>
                                        <form method="POST" action="{{ route('pos.online_orders.reject', ['channelOrder' => $order->id]) }}" class="d-flex gap-1">
                                            @csrf
                                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reject reason" style="max-width: 150px;" {{ ($rejectDisabled || !($queueInteractive ?? false)) ? 'disabled' : '' }}>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" {{ ($rejectDisabled || !($queueInteractive ?? false)) ? 'disabled' : '' }}>Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="invoice-preview-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="invoice-preview-frame" src="about:blank" style="width:100%;height:75vh;border:0;"></iframe>
            </div>
            <div class="modal-footer">
                <a href="#" id="invoice-preview-pdf" class="btn btn-success">Download PDF</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@if(($newOrderCount ?? 0) > 0)
    <div class="modal fade" id="new-orders-alert-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Online Orders</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        <strong>{{ $newOrderCount }}</strong> order(s) are waiting for action.
                    </p>
                    <ul class="mb-0">
                        @foreach($newOrderAlerts as $alertOrder)
                            <li>
                                {{ strtoupper($alertOrder->channel) }} -
                                {{ $alertOrder->external_order_id }} -
                                {{ strtoupper($alertOrder->status) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Review Queue</button>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('scripts')
<script>
(() => {
    if (!window.bootstrap) return;

    const invoiceModalEl = document.getElementById('invoice-preview-modal');
    const invoiceFrame = document.getElementById('invoice-preview-frame');
    const invoicePdf = document.getElementById('invoice-preview-pdf');
    const invoiceModal = invoiceModalEl ? new window.bootstrap.Modal(invoiceModalEl) : null;

    document.querySelectorAll('.js-invoice-preview').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!invoiceModal || !invoiceFrame || !invoicePdf) return;
            const invoiceUrl = btn.getAttribute('data-invoice-url') || 'about:blank';
            const pdfUrl = btn.getAttribute('data-pdf-url') || '#';
            invoiceFrame.src = invoiceUrl;
            invoicePdf.href = pdfUrl;
            invoiceModal.show();
        });
    });

    if (invoiceModalEl && invoiceFrame) {
        invoiceModalEl.addEventListener('hidden.bs.modal', () => {
            invoiceFrame.src = 'about:blank';
        });
    }

    @if(($newOrderCount ?? 0) > 0)
    const modalEl = document.getElementById('new-orders-alert-modal');
    if (!modalEl) return;
    const modal = new window.bootstrap.Modal(modalEl);
    modal.show();
    @endif
})();
</script>
@endsection

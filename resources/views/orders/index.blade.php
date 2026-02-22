@extends('layouts.app')

@section('content')
@php
    $gstEnabled = (bool) (($businessSettings['gst_enabled'] ?? false));
    $kotMode = strtolower((string) ($businessSettings['kot_mode'] ?? 'always'));
    $showKotColumn = $kotMode !== 'off';
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Order Management</h2>
    <button type="button" class="btn btn-primary px-4" id="open-order-modal">Add New Order</button>
</div>

@if(session('success'))
    <div class="alert alert-success fade show py-2 d-flex align-items-center justify-content-between" id="orders-success-alert" role="alert">
        <div>
            {{ session('success') }}
            @if(session('invoice_sale_id'))
                <button type="button" class="btn btn-link alert-link p-0 js-invoice-preview"
                    data-invoice-url="{{ route('pos.sales.invoice', ['sale' => session('invoice_sale_id'), 'embed' => 1]) }}"
                    data-pdf-url="{{ route('pos.sales.invoice.pdf', ['sale' => session('invoice_sale_id')]) }}"
                >Open Invoice</button>
            @endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="GET" action="{{ route('orders.index') }}" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">All Status</option>
            @foreach($orderStatuses as $statusOption)
                @php
                    $statusOptionLabel = match ($statusOption) {
                        'new', 'accepted' => 'RECEIVED',
                        'in_kitchen' => 'UNDER PREPARATION',
                        'ready' => 'READY',
                        'dispatched' => 'DISPATCHED',
                        'completed' => 'COMPLETED',
                        'invoiced' => 'INVOICED',
                        'cancelled' => 'CANCELLED',
                        default => strtoupper((string) $statusOption),
                    };
                @endphp
                <option value="{{ $statusOption }}" {{ $status === $statusOption ? 'selected' : '' }}>{{ $statusOptionLabel }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-7">
        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Order ID / customer / mobile">
    </div>
    <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-outline-dark">Apply</button>
    </div>
</form>

<div class="card">
    <div class="card-header">Order Queue</div>
    <div class="card-body p-0">
        @if($orders->isEmpty())
            <div class="p-3 text-muted">No orders found.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Source</th>
                            <th>Customer</th>
                            <th>Total</th>
                            @if($showKotColumn)
                                <th>KOT</th>
                            @endif
                            <th>Stage</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $statusLabel = strtolower((string) $order->status);
                                $statusDisplay = match ($statusLabel) {
                                    'new', 'accepted' => 'RECEIVED',
                                    'in_kitchen' => 'UNDER PREPARATION',
                                    'ready' => 'READY',
                                    'dispatched' => 'DISPATCHED',
                                    'completed' => 'COMPLETED',
                                    'invoiced' => 'INVOICED',
                                    'cancelled' => 'CANCELLED',
                                    default => strtoupper($statusLabel),
                                };
                                $total = round((float) $order->items->sum('line_total'), 2);
                                $advance = round((float) ($order->advance_paid_amount ?? 0), 2);
                                $advanceMode = strtolower((string) ($order->advance_payment_mode ?? ''));
                                $canConvertInvoice = in_array($statusLabel, ['ready', 'dispatched', 'completed'], true) && !$order->sale_id;
                            @endphp
                            <tr>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-link p-0 fw-semibold text-decoration-none js-open-trail"
                                        data-order-id="{{ $order->id }}"
                                        data-order-number="{{ $order->order_number }}"
                                    >{{ $order->order_number }}</button>
                                    <div class="small text-muted">{{ $order->created_at?->timezone('Asia/Kolkata')->format('Y-m-d h:i A') }} IST</div>
                                </td>
                                <td>{{ $order->source === 'other' ? 'OTHERS' : strtoupper((string) $order->source) }}</td>
                                <td>
                                    {{ $order->customer_name ?: 'Walk-in / Unknown' }}
                                    <div class="small text-muted">{{ $order->customer_identifier ?: '-' }}</div>
                                </td>
                                <td>
                                    Rs.{{ number_format($total, 2) }}
                                    <div class="small text-muted">Adv: Rs.{{ number_format($advance, 2) }} {{ $advanceMode !== '' ? '(' . strtoupper($advanceMode) . ')' : '' }}</div>
                                </td>
                                @if($showKotColumn)
                                    <td>
                                        @if($order->kot)
                                            <a href="{{ route('orders.kot.print', ['order' => $order->id]) }}" class="btn btn-sm btn-outline-dark" target="_blank">{{ $order->kot->kot_number }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                @endif
                                <td>
                                    @php
                                        $transitionActions = [];
                                        if (in_array($statusLabel, ['new', 'accepted'], true)) {
                                            $transitionActions[] = ['type' => 'status', 'label' => 'Under Preparation', 'status' => 'in_kitchen'];
                                        }
                                        if ($statusLabel === 'in_kitchen') {
                                            $transitionActions[] = ['type' => 'status', 'label' => 'Ready', 'status' => 'ready'];
                                        }
                                        if ($canConvertInvoice) {
                                            $transitionActions[] = ['type' => 'convert', 'label' => 'Invoiced'];
                                        } elseif ($order->sale_id && !in_array($statusLabel, ['invoiced', 'cancelled'], true)) {
                                            $transitionActions[] = ['type' => 'status', 'label' => 'Invoiced', 'status' => 'invoiced'];
                                        }
                                        if (!in_array($statusLabel, ['invoiced', 'cancelled'], true) && !$order->sale_id) {
                                            $transitionActions[] = ['type' => 'status', 'label' => 'Cancelled', 'status' => 'cancelled'];
                                        }
                                        if ($statusLabel === 'ready') {
                                            $transitionActions[] = ['type' => 'status', 'label' => 'Dispatched', 'status' => 'dispatched'];
                                        }
                                    @endphp
                                    @if(!empty($transitionActions))
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                {{ $statusDisplay }}
                                            </button>
                                            <ul class="dropdown-menu">
                                                @foreach($transitionActions as $action)
                                                    @if($action['type'] === 'convert')
                                                        <li>
                                                            <button
                                                                type="button"
                                                                class="dropdown-item js-open-convert"
                                                                data-order-id="{{ $order->id }}"
                                                                data-order-number="{{ $order->order_number }}"
                                                                data-order-total="{{ number_format($total, 2, '.', '') }}"
                                                                data-order-advance="{{ number_format($advance, 2, '.', '') }}"
                                                                data-order-advance-mode="{{ $advanceMode }}"
                                                                data-customer="{{ $order->customer_name ?: 'Walk-in / Unknown' }}"
                                                            >{{ $action['label'] }}</button>
                                                        </li>
                                                    @else
                                                        <li>
                                                            <button
                                                                type="button"
                                                                class="dropdown-item js-order-status"
                                                                data-order-id="{{ $order->id }}"
                                                                data-status="{{ $action['status'] }}"
                                                            >{{ $action['label'] }}</button>
                                                        </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                            <form method="POST" action="{{ route('orders.status', ['order' => $order->id]) }}" class="d-none" id="order-status-form-{{ $order->id }}">
                                                @csrf
                                                <input type="hidden" name="status" value="">
                                            </form>
                                        </div>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>{{ $statusDisplay }}</button>
                                    @endif
                                </td>
                                <td>
                                    @if($order->sale)
                                        <button type="button" class="btn btn-sm btn-outline-success js-invoice-preview"
                                            data-invoice-url="{{ route('pos.sales.invoice', ['sale' => $order->sale->id, 'embed' => 1]) }}"
                                            data-pdf-url="{{ route('pos.sales.invoice.pdf', ['sale' => $order->sale->id]) }}"
                                        >{{ $order->sale->bill_number }}</button>
                                    @elseif($canConvertInvoice)
                                        <span class="badge text-bg-warning">Pending</span>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="order-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('orders.store') }}" id="order-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add New Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body order-modal-body">
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="row g-2 mb-2">
                                <div class="col-md-8">
                                    <div class="position-relative">
                                        <input id="product-code" class="form-control" autocomplete="off" placeholder="FG001 / 01 / Product name">
                                        <div id="product-suggestions" class="list-group position-absolute w-100 shadow-sm" style="z-index:20;max-height:240px;overflow-y:auto;display:none;"></div>
                                    </div>
                                </div>
                                <div class="col-md-4 d-grid"><button type="button" id="add-item" class="btn btn-outline-primary">Add Item</button></div>
                            </div>
                            <div id="item-msg" class="small mb-2"></div>
                            <div class="card">
                                <div class="card-header">Cart Items</div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Code</th><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th><th></th></tr></thead>
                                        <tbody id="cart-body"><tr id="cart-empty"><td colspan="6" class="text-center text-muted py-3">No items added yet.</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="small mt-2">Total: Rs.<span id="cart-total">0.00</span></div>
                            <div id="items-inputs"></div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card">
                                <div class="card-header">Order Context</div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label">Source</label>
                                        <select name="source" id="source" class="form-select">
                                            @foreach($orderSources as $source)
                                                <option value="{{ $source }}" {{ old('source', 'outlet') === $source ? 'selected' : '' }}>{{ $source === 'other' ? 'OTHERS' : strtoupper($source) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-8">
                                            <label class="form-label">Customer Mobile</label>
                                            <input type="text" name="customer_identifier" id="customer_identifier" class="form-control" value="{{ old('customer_identifier') }}" maxlength="10" inputmode="numeric">
                                        </div>
                                        <div class="col-4 d-grid">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" id="customer_lookup_btn" class="btn btn-outline-secondary">Check</button>
                                        </div>
                                    </div>
                                    <div class="mb-2"><label class="form-label">Customer Name</label><input type="text" name="customer_name" id="customer_name" class="form-control" value="{{ old('customer_name') }}"></div>
                                    <input type="hidden" name="customer_address" id="customer_address" value="{{ old('customer_address') }}">
                                    <div id="address-group">
                                        <div class="mb-2"><label class="form-label">Apartment / House</label><input type="text" name="customer_address_line1" id="address_line1" class="form-control" value="{{ old('customer_address_line1') }}"></div>
                                        <div class="mb-2"><label class="form-label">Road</label><input type="text" name="customer_road" id="road" class="form-control" value="{{ old('customer_road') }}"></div>
                                        <div class="mb-2"><label class="form-label">Sector</label><input type="text" name="customer_sector" id="sector" class="form-control" value="{{ old('customer_sector') }}"></div>
                                        <div class="mb-2"><label class="form-label">City</label><input type="text" name="customer_city" id="city" class="form-control" value="{{ old('customer_city') }}"></div>
                                        <div class="mb-2"><label class="form-label">Pincode</label><input type="text" name="customer_pincode" id="pincode" class="form-control" value="{{ old('customer_pincode') }}" maxlength="6" inputmode="numeric"></div>
                                    </div>
                                    <div class="mb-2"><label class="form-label">Preference</label><input type="text" name="customer_preference" id="preference" class="form-control" value="{{ old('customer_preference') }}"></div>
                                    <div class="mb-2"><label class="form-label">Notes</label><input type="text" name="notes" id="notes" class="form-control" value="{{ old('notes') }}"></div>
                                    <div class="row g-2">
                                        <div class="col-6"><label class="form-label">Advance Paid</label><input type="number" step="0.01" min="0" name="advance_paid_amount" id="advance_paid" class="form-control" value="{{ old('advance_paid_amount', '0') }}"></div>
                                        <div class="col-6"><label class="form-label">Advance Mode</label><select name="advance_payment_mode" id="advance_mode" class="form-select"><option value="">Select</option><option value="cash" {{ old('advance_payment_mode') === 'cash' ? 'selected' : '' }}>Cash</option><option value="upi" {{ old('advance_payment_mode') === 'upi' ? 'selected' : '' }}>UPI</option><option value="card" {{ old('advance_payment_mode') === 'card' ? 'selected' : '' }}>Card</option></select></div>
                                    </div>
                                    <div id="customer_lookup_result" class="small text-muted mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="create-order-btn">Create Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="convert-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('pos.checkout') }}">
                @csrf
                <input type="hidden" name="order_id" id="convert_order_id">
                <input type="hidden" name="submit_action" value="invoice">
                <div class="modal-header"><h5 class="modal-title">Convert to Invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        <div><strong>Order:</strong> <span id="convert_order_number">-</span></div>
                        <div><strong>Customer:</strong> <span id="convert_customer">-</span></div>
                        <div><strong>Total:</strong> Rs.<span id="convert_total">0.00</span></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Payment Mode</label><select name="payment_mode" id="convert_payment_mode" class="form-select" required><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option></select></div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label">Discount</label><input type="number" min="0" step="0.01" name="discount_amount" class="form-control" value="0"></div>
                        <div class="col-6" style="{{ $gstEnabled ? '' : 'display:none;' }}"><label class="form-label">Tax</label><input type="number" min="0" step="0.01" name="tax_amount" class="form-control" value="0" {{ $gstEnabled ? '' : 'readonly' }}></div>
                        <div class="col-6"><label class="form-label">Round Off</label><input type="number" step="0.01" name="round_off" class="form-control" value="0"></div>
                        <div class="col-6"><label class="form-label">Paid</label><input type="number" min="0" step="0.01" name="paid_amount" id="convert_paid" class="form-control" value=""></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Create Invoice</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="repeat-customer-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="repeat-customer-title">Customer Lookup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="repeat-customer-summary" class="mb-2"></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="mb-2">Previous Orders</h6>
                        <ul id="repeat-customer-orders" class="mb-0 small"></ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-2">Favorite Items</h6>
                        <ul id="repeat-customer-favorites" class="mb-0 small"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="order-trail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Audit Trail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 small text-muted">
                    <strong>Order:</strong> <span id="trail-order-number">-</span>
                </div>
                <ul class="list-group list-group-flush" id="trail-list"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="invoice-preview-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Invoice Preview</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0"><iframe id="invoice-frame" src="about:blank" style="width:100%;height:75vh;border:0;"></iframe></div>
            <div class="modal-footer"><a href="#" id="invoice-pdf" class="btn btn-success">Download PDF</a><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
    #order-modal .order-modal-body {
        max-height: 72vh;
        overflow-y: auto;
    }
</style>
<script>
(() => {
    if (!window.bootstrap) return;

    const searchUrl = @json(route('pos.products.search'));
    const lookupUrl = @json(route('pos.products.lookup'));
    const customerLookupUrl = @json(route('pos.customers.lookup'));
    const initialItems = @json($initialItems ?? []);
    const orderTrailById = @json($orderTrailById ?? []);

    const orderModalEl = document.getElementById('order-modal');
    const orderModal = orderModalEl ? new bootstrap.Modal(orderModalEl) : null;
    const convertModalEl = document.getElementById('convert-modal');
    const convertModal = convertModalEl ? new bootstrap.Modal(convertModalEl) : null;
    const previewModalEl = document.getElementById('invoice-preview-modal');
    const previewModal = previewModalEl ? new bootstrap.Modal(previewModalEl) : null;
    const repeatModalEl = document.getElementById('repeat-customer-modal');
    const repeatModal = repeatModalEl ? new bootstrap.Modal(repeatModalEl) : null;
    const trailModalEl = document.getElementById('order-trail-modal');
    const trailModal = trailModalEl ? new bootstrap.Modal(trailModalEl) : null;

    const openBtn = document.getElementById('open-order-modal');
    const form = document.getElementById('order-form');
    const productCode = document.getElementById('product-code');
    const addItem = document.getElementById('add-item');
    const itemMsg = document.getElementById('item-msg');
    const suggestionsEl = document.getElementById('product-suggestions');

    const cartBody = document.getElementById('cart-body');
    const cartEmpty = document.getElementById('cart-empty');
    const cartTotal = document.getElementById('cart-total');
    const itemsInputs = document.getElementById('items-inputs');

    const source = document.getElementById('source');
    const customerIdentifier = document.getElementById('customer_identifier');
    const customerLookupBtn = document.getElementById('customer_lookup_btn');
    const customerLookupResult = document.getElementById('customer_lookup_result');
    const customerName = document.getElementById('customer_name');
    const addressGroup = document.getElementById('address-group');
    const addressLine1 = document.getElementById('address_line1');
    const road = document.getElementById('road');
    const sector = document.getElementById('sector');
    const city = document.getElementById('city');
    const pincode = document.getElementById('pincode');
    const preference = document.getElementById('preference');
    const customerAddress = document.getElementById('customer_address');

    const advancePaid = document.getElementById('advance_paid');
    const advanceMode = document.getElementById('advance_mode');
    const createOrderBtn = document.getElementById('create-order-btn');

    const convertOrderId = document.getElementById('convert_order_id');
    const convertOrderNumber = document.getElementById('convert_order_number');
    const convertCustomer = document.getElementById('convert_customer');
    const convertTotal = document.getElementById('convert_total');
    const convertPaid = document.getElementById('convert_paid');
    const convertPaymentMode = document.getElementById('convert_payment_mode');

    const invoiceFrame = document.getElementById('invoice-frame');
    const invoicePdf = document.getElementById('invoice-pdf');
    const repeatTitle = document.getElementById('repeat-customer-title');
    const repeatSummary = document.getElementById('repeat-customer-summary');
    const repeatOrders = document.getElementById('repeat-customer-orders');
    const repeatFavorites = document.getElementById('repeat-customer-favorites');
    const successAlertEl = document.getElementById('orders-success-alert');
    const trailOrderNumber = document.getElementById('trail-order-number');
    const trailList = document.getElementById('trail-list');

    const cart = new Map();
    let suggestions = [];
    let suggestionIndex = -1;
    let searchTimer = null;

    const num = (value) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (match) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[match]));
    const codeLabel = (item) => String(
        item?.display_code
        || ((item?.legacy_code && String(item.legacy_code).trim() !== '') ? `${item.code} | ${item.legacy_code}` : (item?.code || ''))
    );

    const setMsg = (message, isError = false) => {
        itemMsg.textContent = message || '';
        itemMsg.className = isError ? 'small mb-2 text-danger' : 'small mb-2 text-success';
    };

    const setCustomerLookupMessage = (message, kind = 'muted') => {
        customerLookupResult.innerHTML = message || '';
        customerLookupResult.className = kind === 'error'
            ? 'small text-danger mt-2'
            : (kind === 'repeat' ? 'small text-warning mt-2' : 'small text-muted mt-2');
    };

    const normalizeIdentifier = (value) => {
        const raw = (value || '').trim();
        if (!raw) return '';
        const digits = raw.replace(/\D/g, '');
        if (digits.length >= 10) return digits.slice(-10);
        if (digits.length > 0) return digits;
        return raw.toUpperCase();
    };

    const syncAddress = () => {
        const pin = (pincode.value || '').replace(/\D/g, '').slice(0, 6);
        pincode.value = pin;
        const parts = [addressLine1.value, road.value, sector.value, city.value]
            .map((value) => String(value || '').trim())
            .filter((value) => value !== '');
        customerAddress.value = parts.join(', ') + (pin ? (parts.length ? ' - ' : '') + pin : '');
    };

    const setAddressFields = (profile = {}, overwrite = false) => {
        const map = [
            ['address_line1', addressLine1],
            ['road', road],
            ['sector', sector],
            ['city', city],
            ['pincode', pincode],
            ['preference', preference],
        ];
        map.forEach(([key, input]) => {
            if (!input) return;
            const value = String(profile[key] ?? '').trim();
            if (!value) return;
            if (overwrite || !String(input.value || '').trim()) {
                input.value = value;
            }
        });
        syncAddress();
    };

    const isPhoneOrWhatsappSource = () => {
        const src = (source.value || 'outlet').toLowerCase();
        return src === 'phone' || src === 'whatsapp';
    };

    const applySource = () => {
        const delivery = (source.value || 'outlet') !== 'outlet';
        customerIdentifier.required = delivery;
        customerName.required = delivery;
        addressLine1.required = delivery;
        addressGroup.style.display = delivery ? '' : 'none';
        syncAddress();
    };

    const upsert = (product, quantityToAdd) => {
        const key = String(product.id);
        if (cart.has(key)) {
            const existing = cart.get(key);
            existing.quantity += quantityToAdd;
            existing.price = num(product.price);
            existing.code = codeLabel(product);
            existing.name = String(product.name || '');
            cart.set(key, existing);
        } else {
            cart.set(key, {
                id: Number(product.id),
                code: codeLabel(product),
                name: String(product.name || ''),
                price: num(product.price),
                quantity: quantityToAdd,
            });
        }
    };

    const clearSuggestions = () => {
        suggestions = [];
        suggestionIndex = -1;
        suggestionsEl.innerHTML = '';
        suggestionsEl.style.display = 'none';
    };

    const renderSuggestions = () => {
        suggestionsEl.innerHTML = '';
        if (!suggestions.length) {
            suggestionsEl.style.display = 'none';
            return;
        }
        suggestions.forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action py-2' + (index === suggestionIndex ? ' active' : '');
            button.innerHTML = `<div class="d-flex justify-content-between"><div><strong>${esc(codeLabel(item))}</strong> - ${esc(item.name)}</div><small>Rs.${num(item.price).toFixed(2)}</small></div>`;
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                upsert(item, 1);
                render();
                productCode.value = '';
                setMsg(`${codeLabel(item)} - ${item.name} added.`);
                clearSuggestions();
                productCode.focus();
            });
            suggestionsEl.appendChild(button);
        });
        suggestionsEl.style.display = 'block';
    };

    const render = () => {
        Array.from(cartBody.querySelectorAll('tr[data-id]')).forEach((row) => row.remove());
        let total = 0;
        let count = 0;
        let index = 0;
        itemsInputs.innerHTML = '';

        cart.forEach((item, key) => {
            if (item.quantity <= 0) return;
            count += 1;
            total += item.quantity * item.price;

            const row = document.createElement('tr');
            row.dataset.id = key;
            row.innerHTML = `<td>${esc(item.code)}</td><td>${esc(item.name)}</td><td><input type="number" min="0" step="1" class="form-control form-control-sm qty" data-id="${key}" value="${item.quantity}"></td><td>Rs.${item.price.toFixed(2)}</td><td>Rs.${(item.quantity * item.price).toFixed(2)}</td><td><button type="button" class="btn btn-sm btn-outline-danger rm" data-id="${key}">x</button></td>`;
            cartBody.appendChild(row);

            itemsInputs.insertAdjacentHTML('beforeend', `<input type="hidden" name="items[${index}][product_id]" value="${item.id}"><input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">`);
            index += 1;
        });

        cartEmpty.classList.toggle('d-none', count > 0);
        cartTotal.textContent = total.toFixed(2);
        createOrderBtn.disabled = count === 0;
    };

    const searchProducts = async (term) => {
        const query = (term || '').trim();
        if (!query) {
            clearSuggestions();
            return;
        }
        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok || !Array.isArray(payload)) {
                clearSuggestions();
                return;
            }
            suggestions = payload;
            suggestionIndex = payload.length ? 0 : -1;
            renderSuggestions();
        } catch (error) {
            clearSuggestions();
        }
    };

    const addByCode = async () => {
        const code = (productCode.value || '').trim();
        if (!code) {
            setMsg('Enter product code.', true);
            return;
        }
        addItem.disabled = true;
        setMsg('Searching...');
        try {
            const response = await fetch(`${lookupUrl}?code=${encodeURIComponent(code)}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error((payload && payload.message) || 'Product not found.');
            }
            upsert(payload, 1);
            render();
            productCode.value = '';
            setMsg(`${codeLabel(payload)} - ${payload.name} added.`);
            clearSuggestions();
        } catch (error) {
            setMsg(error.message || 'Unable to add product.', true);
        } finally {
            addItem.disabled = false;
        }
    };

    const renderCustomerPopup = (payload, identifier) => {
        const stats = payload?.stats || { sales_count: 0, total_spent: 0, last_sale: null };
        const sales = Array.isArray(payload?.recent_sales) ? payload.recent_sales : [];
        const favorites = Array.isArray(payload?.favorite_items) ? payload.favorite_items : [];
        const customer = payload?.customer || null;

        const customerNameValue = ((customer && customer.name) || stats?.last_sale?.customer_name || '').trim();
        const customerAddressValue = ((customer && customer.address) || stats?.last_sale?.customer_address || '').trim();

        repeatTitle.textContent = (stats.sales_count || 0) > 0 ? 'Repeat Customer Found' : 'Customer Lookup';
        repeatSummary.innerHTML = `<div><strong>Identifier:</strong> ${esc(identifier || '-')}</div><div><strong>Name:</strong> ${esc(customerNameValue || 'Not available')}</div><div><strong>Address:</strong> ${esc(customerAddressValue || 'Not available')}</div><div><strong>Preference:</strong> ${esc(customer?.preference || 'Not available')}</div><div class="mt-1"><strong>Visits:</strong> ${stats.sales_count || 0} | <strong>Lifetime Spend:</strong> Rs.${num(stats.total_spent).toFixed(2)}</div>${stats.last_sale ? `<div><strong>Last Sale:</strong> ${esc(stats.last_sale.bill_number)} on ${esc(stats.last_sale.date || '')}</div>` : '<div><strong>Last Sale:</strong> Not available</div>'}`;
        repeatOrders.innerHTML = sales.length
            ? sales.map((sale) => `<li>${esc(sale.bill_number)} | ${esc((sale.order_source || '').toUpperCase())} | Rs.${num(sale.total_amount).toFixed(2)} | ${esc(sale.date || '')}</li>`).join('')
            : '<li>No previous invoices found.</li>';
        repeatFavorites.innerHTML = favorites.length
            ? favorites.map((item) => `<li>${esc(item.code)} - ${esc(item.name)} | Qty ${num(item.total_qty).toFixed(2)} | Orders ${item.order_count}</li>`).join('')
            : '<li>No favorite items captured yet.</li>';

        repeatModal.show();
    };

    const lookupCustomer = async (showPopup = true) => {
        const identifier = normalizeIdentifier(customerIdentifier.value);
        if (!identifier) {
            setCustomerLookupMessage('Enter mobile/identifier to check purchase history.', 'error');
            return;
        }

        customerLookupBtn.disabled = true;
        setCustomerLookupMessage('Checking purchase history...');
        try {
            const response = await fetch(`${customerLookupUrl}?identifier=${encodeURIComponent(identifier)}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error((payload && payload.message) || 'Unable to lookup customer.');
            }

            const customer = payload?.customer || null;
            if (customer) {
                if (isPhoneOrWhatsappSource()) {
                    customerName.value = String(customer.name || '').trim();
                } else if (!String(customerName.value || '').trim()) {
                    customerName.value = String(customer.name || '').trim();
                }
                setAddressFields({
                    address_line1: customer.address_line1 || '',
                    road: customer.road || '',
                    sector: customer.sector || '',
                    city: customer.city || '',
                    pincode: customer.pincode || '',
                    preference: customer.preference || '',
                }, isPhoneOrWhatsappSource());
            }

            const hasContext = Boolean(customer || payload?.stats?.last_sale || (payload?.stats?.sales_count || 0) > 0);
            if (!hasContext) {
                setCustomerLookupMessage('New Customer!!');
                return;
            }

            setCustomerLookupMessage('Customer context loaded. Details available in popup.', 'repeat');
            if (showPopup) {
                renderCustomerPopup(payload, identifier);
            }
        } catch (error) {
            setCustomerLookupMessage(esc(error.message || 'Unable to lookup customer.'), 'error');
        } finally {
            customerLookupBtn.disabled = false;
        }
    };

    openBtn && openBtn.addEventListener('click', () => orderModal && orderModal.show());

    addItem && addItem.addEventListener('click', () => {
        if (suggestionIndex >= 0 && suggestions[suggestionIndex]) {
            const item = suggestions[suggestionIndex];
            upsert(item, 1);
            render();
            productCode.value = '';
            setMsg(`${codeLabel(item)} - ${item.name} added.`);
            clearSuggestions();
            return;
        }
        addByCode();
    });

    productCode && productCode.addEventListener('input', () => {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(() => searchProducts(productCode.value), 170);
    });

    productCode && productCode.addEventListener('keydown', (event) => {
        if (!suggestions.length) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addByCode();
            }
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            suggestionIndex = (suggestionIndex + 1) % suggestions.length;
            renderSuggestions();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            suggestionIndex = (suggestionIndex - 1 + suggestions.length) % suggestions.length;
            renderSuggestions();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (suggestionIndex >= 0 && suggestions[suggestionIndex]) {
                const item = suggestions[suggestionIndex];
                upsert(item, 1);
                render();
                productCode.value = '';
                setMsg(`${codeLabel(item)} - ${item.name} added.`);
                clearSuggestions();
            }
        } else if (event.key === 'Escape') {
            clearSuggestions();
        }
    });

    cartBody && cartBody.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (target.classList.contains('rm')) {
            cart.delete(String(target.dataset.id || ''));
            render();
        }
    });

    cartBody && cartBody.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) return;
        if (!target.classList.contains('qty')) return;
        const id = String(target.dataset.id || '');
        if (!cart.has(id)) return;
        const item = cart.get(id);
        item.quantity = Math.max(0, Math.floor(num(target.value)));
        cart.set(id, item);
        render();
    });

    source && source.addEventListener('change', applySource);
    [addressLine1, road, sector, city, pincode].forEach((element) => {
        element && element.addEventListener('input', syncAddress);
    });

    customerIdentifier && customerIdentifier.addEventListener('input', () => {
        customerIdentifier.value = (customerIdentifier.value || '').replace(/\D/g, '').slice(0, 10);
        setCustomerLookupMessage('');
    });
    customerIdentifier && customerIdentifier.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookupCustomer(true);
        }
    });
    customerIdentifier && customerIdentifier.addEventListener('blur', () => {
        if ((customerIdentifier.value || '').trim() !== '') {
            lookupCustomer(false);
        }
    });
    customerLookupBtn && customerLookupBtn.addEventListener('click', () => lookupCustomer(true));

    form && form.addEventListener('submit', (event) => {
        syncAddress();
        if (num(advancePaid.value) > 0 && !advanceMode.value) {
            event.preventDefault();
            setMsg('Select advance mode when advance is entered.', true);
            return;
        }
        const hasItems = Array.from(cart.values()).some((item) => item.quantity > 0);
        if (!hasItems) {
            event.preventDefault();
            setMsg('Add at least one item.', true);
        }
    });

    document.querySelectorAll('.js-open-convert').forEach((btn) => btn.addEventListener('click', () => {
        convertOrderId.value = btn.dataset.orderId || '';
        convertOrderNumber.textContent = btn.dataset.orderNumber || '-';
        convertCustomer.textContent = btn.dataset.customer || '-';
        const total = num(btn.dataset.orderTotal || 0);
        const advance = num(btn.dataset.orderAdvance || 0);
        const mode = (btn.dataset.orderAdvanceMode || '').toLowerCase();
        convertTotal.textContent = total.toFixed(2);
        convertPaid.value = (advance > 0 ? advance : total).toFixed(2);
        convertPaymentMode.value = ['cash', 'upi', 'card'].includes(mode) ? mode : 'cash';
        convertModal && convertModal.show();
    }));

    document.querySelectorAll('.js-open-trail').forEach((btn) => btn.addEventListener('click', () => {
        if (!trailModal || !trailOrderNumber || !trailList) return;
        const orderId = String(btn.getAttribute('data-order-id') || '');
        const orderNumber = String(btn.getAttribute('data-order-number') || '-');
        const events = Array.isArray(orderTrailById[orderId]) ? orderTrailById[orderId] : [];

        trailOrderNumber.textContent = orderNumber;
        trailList.innerHTML = '';
        if (!events.length) {
            trailList.innerHTML = '<li class="list-group-item text-muted">No audit events available.</li>';
            trailModal.show();
            return;
        }

        events.forEach((event) => {
            const label = String(event.label || 'Updated');
            const timeIst = String(event.time_ist || '');
            const note = String(event.note || '');
            const actor = String(event.actor || '');
            const item = document.createElement('li');
            item.className = 'list-group-item';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold">${esc(label)}</div>
                        ${note ? `<div class="small text-muted">${esc(note)}</div>` : ''}
                        ${actor ? `<div class="small text-muted">By: ${esc(actor)}</div>` : ''}
                    </div>
                    <div class="small text-muted text-nowrap ms-2">${esc(timeIst)}</div>
                </div>
            `;
            trailList.appendChild(item);
        });

        trailModal.show();
    }));

    document.querySelectorAll('.js-invoice-preview').forEach((btn) => btn.addEventListener('click', () => {
        invoiceFrame.src = btn.dataset.invoiceUrl || 'about:blank';
        invoicePdf.href = btn.dataset.pdfUrl || '#';
        previewModal && previewModal.show();
    }));

    document.querySelectorAll('.js-order-status').forEach((btn) => btn.addEventListener('click', () => {
        const orderId = btn.getAttribute('data-order-id');
        const nextStatus = btn.getAttribute('data-status');
        if (!orderId || !nextStatus) return;
        const formEl = document.getElementById(`order-status-form-${orderId}`);
        if (!(formEl instanceof HTMLFormElement)) return;
        const statusInput = formEl.querySelector('input[name="status"]');
        if (!(statusInput instanceof HTMLInputElement)) return;
        statusInput.value = nextStatus;
        formEl.submit();
    }));

    previewModalEl && previewModalEl.addEventListener('hidden.bs.modal', () => {
        invoiceFrame.src = 'about:blank';
    });

    if (successAlertEl) {
        setTimeout(() => {
            if (!successAlertEl.classList.contains('show')) return;
            const alert = bootstrap.Alert.getOrCreateInstance(successAlertEl);
            alert.close();
        }, 10000);
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        if (target.closest('#product-suggestions') || target.closest('#product-code')) return;
        clearSuggestions();
    });

    initialItems.forEach((item) => {
        const quantity = Math.max(0, Math.floor(num(item.quantity)));
        if (quantity > 0) upsert(item, quantity);
    });
    applySource();
    render();

    @if(old('source') || old('items'))
        if (orderModal) orderModal.show();
    @endif
})();
</script>
@endsection

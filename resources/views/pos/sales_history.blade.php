@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Invoice History</h2>
</div>

<div class="card mb-3">
    <div class="card-header">Filters</div>
    <div class="card-body">
        @php
            $showAdvancedFilters = ($quickRange ?? '') === 'custom'
                || trim((string) ($orderSource ?? '')) !== ''
                || trim((string) ($dateFrom ?? '')) !== ''
                || trim((string) ($dateTo ?? '')) !== '';
            $rangeButtons = [
                'today' => 'Today',
                'last_7_days' => 'Last 7 Days',
                'this_month' => 'This Month',
            ];
        @endphp
        <form method="GET" action="{{ route('pos.sales.index') }}" class="row g-2" id="invoice-history-filter-form">
            <input type="hidden" name="quick_range" id="quick_range" value="{{ $quickRange ?? '' }}">
            <div class="col-md-4">
                <label class="form-label mb-1">Invoice No</label>
                <input type="text" name="invoice" value="{{ $invoice }}" class="form-control" placeholder="INV-YYYYMMDD-00001">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Customer</label>
                <input type="text" name="customer_identifier" value="{{ $customerIdentifier }}" class="form-control" placeholder="Mobile / Identifier / Name">
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-dark flex-grow-1">Apply</button>
                <a href="{{ route('pos.sales.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
            <div class="col-12 d-flex flex-wrap align-items-center gap-2 mt-2">
                @foreach($rangeButtons as $rangeKey => $rangeLabel)
                    <button type="button"
                        class="btn btn-sm {{ ($quickRange ?? '') === $rangeKey ? 'btn-primary' : 'btn-outline-primary' }} js-quick-range"
                        data-range="{{ $rangeKey }}"
                    >{{ $rangeLabel }}</button>
                @endforeach
                <button type="button"
                    class="btn btn-sm {{ ($quickRange ?? '') === 'custom' ? 'btn-primary' : 'btn-outline-primary' }} js-quick-range"
                    data-range="custom"
                >Custom Range</button>
                <button
                    class="btn btn-sm btn-outline-secondary ms-auto"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#invoice-history-advanced-filters"
                    aria-expanded="{{ $showAdvancedFilters ? 'true' : 'false' }}"
                    aria-controls="invoice-history-advanced-filters"
                >More Filters</button>
            </div>
            <div class="col-12">
                <div class="collapse {{ $showAdvancedFilters ? 'show' : '' }}" id="invoice-history-advanced-filters">
                    <div class="row g-2 mt-1">
                        <div class="col-md-3">
                            <label class="form-label mb-1">Date From</label>
                            <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Date To</label>
                            <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Source</label>
                            <select name="order_source" class="form-select">
                                <option value="">All sources</option>
                                @foreach($orderSources as $source)
                                    <option value="{{ $source }}" {{ $orderSource === $source ? 'selected' : '' }}>{{ strtoupper($source) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@php
    $invoiceCount = $sales->count();
    $paidTotal = (float) $sales->sum('paid_amount');
    $balanceTotal = (float) $sales->sum('balance_amount');
@endphp

<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Invoices</div>
                <div class="h5 mb-0">{{ $invoiceCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Paid</div>
                <div class="h5 mb-0 text-success">Rs.{{ number_format($paidTotal, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="small text-muted">Balance Due</div>
                <div class="h5 mb-0 {{ $balanceTotal > 0 ? 'text-danger' : 'text-success' }}">Rs.{{ number_format($balanceTotal, 2) }}</div>
            </div>
        </div>
    </div>
</div>

@if($sales->isEmpty())
    <div class="alert alert-info">No sales found for selected filters.</div>
@else
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle invoice-history-table">
                    <colgroup>
                        <col style="width: 18%;">
                        <col style="width: 14%;">
                        <col style="width: 24%;">
                        <col style="width: 10%;">
                        <col style="width: 10%;">
                        <col style="width: 8%;">
                        <col style="width: 8%;">
                        <col style="width: 8%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date/Time</th>
                            <th>Customer</th>
                            <th>Source</th>
                            <th>Payment</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sales as $sale)
                            <tr>
                                <td class="invoice-col">
                                    <button
                                        type="button"
                                        class="btn btn-link p-0 fw-semibold text-decoration-none js-invoice-preview"
                                        data-invoice-url="{{ route('pos.sales.invoice', ['sale' => $sale->id, 'embed' => 1]) }}"
                                        data-pdf-url="{{ route('pos.sales.invoice.pdf', ['sale' => $sale->id]) }}"
                                    >{{ $sale->bill_number }}</button>
                                    @if($sale->order_reference)
                                        <div class="small text-muted">{{ $sale->order_reference }}</div>
                                    @endif
                                </td>
                                <td class="date-col">{{ $sale->created_at->format('Y-m-d H:i') }}</td>
                                <td class="customer-col">
                                    <div>{{ $sale->customer?->name ?: ($sale->customer_name_snapshot ?: '-') }}</div>
                                    <div class="small text-muted">{{ $sale->customer_identifier ?: ($sale->customer?->mobile ?? $sale->customer?->identifier ?? '-') }}</div>
                                </td>
                                <td><span class="badge text-bg-light border">{{ strtoupper($sale->order_source ?: 'outlet') }}</span></td>
                                <td>
                                    <span class="badge {{ strtolower((string) $sale->payment_mode) === 'cash' ? 'text-bg-success' : (strtolower((string) $sale->payment_mode) === 'upi' ? 'text-bg-primary' : 'text-bg-dark') }}">
                                        {{ strtoupper($sale->payment_mode) }}
                                    </span>
                                </td>
                                <td class="text-end">Rs.{{ number_format($sale->total_amount, 2) }}</td>
                                <td class="text-end">Rs.{{ number_format($sale->paid_amount, 2) }}</td>
                                <td class="text-end {{ (float) $sale->balance_amount > 0 ? 'text-danger' : 'text-success' }}">Rs.{{ number_format($sale->balance_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

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
@endsection

@section('scripts')
<style>
.invoice-history-table {
    table-layout: fixed;
}

.invoice-history-table th,
.invoice-history-table td {
    padding: 0.55rem 0.6rem;
}

.invoice-history-table .invoice-col {
    min-width: 170px;
}

.invoice-history-table .date-col {
    white-space: nowrap;
}

.invoice-history-table .customer-col {
    min-width: 220px;
    white-space: normal;
}

@media (max-width: 992px) {
    .invoice-history-table {
        table-layout: auto;
    }
}
</style>
<script>
(() => {
    if (!window.bootstrap) return;

    const filterForm = document.getElementById('invoice-history-filter-form');
    const quickRangeInput = document.getElementById('quick_range');
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');
    const advancedFiltersEl = document.getElementById('invoice-history-advanced-filters');
    const advancedFilters = advancedFiltersEl ? new window.bootstrap.Collapse(advancedFiltersEl, { toggle: false }) : null;

    if (filterForm && quickRangeInput) {
        document.querySelectorAll('.js-quick-range').forEach((btn) => {
            btn.addEventListener('click', () => {
                const range = btn.getAttribute('data-range') || '';
                quickRangeInput.value = range;
                if (range === 'custom') {
                    advancedFilters && advancedFilters.show();
                    return;
                }
                if (dateFromInput) dateFromInput.value = '';
                if (dateToInput) dateToInput.value = '';
                filterForm.submit();
            });
        });

        [dateFromInput, dateToInput].forEach((input) => {
            if (!input) return;
            input.addEventListener('change', () => {
                quickRangeInput.value = 'custom';
            });
        });

        filterForm.addEventListener('submit', () => {
            const hasDateRange = Boolean((dateFromInput && dateFromInput.value) || (dateToInput && dateToInput.value));
            if (hasDateRange) {
                quickRangeInput.value = 'custom';
            }
        });
    }

    const modalEl = document.getElementById('invoice-preview-modal');
    if (!modalEl) return;

    const frame = document.getElementById('invoice-preview-frame');
    const pdfBtn = document.getElementById('invoice-preview-pdf');
    const modal = new window.bootstrap.Modal(modalEl);
    document.querySelectorAll('.js-invoice-preview').forEach((btn) => {
        btn.addEventListener('click', () => {
            const invoiceUrl = btn.getAttribute('data-invoice-url') || 'about:blank';
            const pdfUrl = btn.getAttribute('data-pdf-url') || '#';
            frame.src = invoiceUrl;
            pdfBtn.href = pdfUrl;
            modal.show();
        });
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        frame.src = 'about:blank';
    });
})();
</script>
@endsection

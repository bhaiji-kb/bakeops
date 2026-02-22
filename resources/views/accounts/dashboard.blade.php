@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Accounts Dashboard</h2>
        <div class="small text-muted">
            Period: {{ $periodLabel }} | Compared with: {{ $previousPeriodLabel }}
        </div>
    </div>
    @php
        $exportQuery = [
            'range_type' => $rangeType,
            'date' => $dateInput,
            'start_date' => $startDateInput,
            'end_date' => $endDateInput,
        ];
    @endphp
    <div class="d-flex gap-2">
        <a href="{{ route('accounts.dashboard.export.pdf', $exportQuery) }}" class="btn btn-sm btn-outline-secondary">Export PDF</a>
        <a href="{{ route('accounts.dashboard.export.excel', $exportQuery) }}" class="btn btn-sm btn-outline-success">Export Excel</a>
    </div>
</div>

<form method="GET" action="{{ route('accounts.dashboard') }}" class="row g-2 mb-3 align-items-end">
    <div class="col-md-2">
        <label class="form-label mb-1">Range</label>
        <select name="range_type" id="range_type" class="form-select">
            <option value="day" {{ $rangeType === 'day' ? 'selected' : '' }}>Day</option>
            <option value="week" {{ $rangeType === 'week' ? 'selected' : '' }}>Week</option>
            <option value="month" {{ $rangeType === 'month' ? 'selected' : '' }}>Month</option>
            <option value="custom" {{ $rangeType === 'custom' ? 'selected' : '' }}>Custom</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label mb-1">Anchor Date</label>
        <input type="date" class="form-control" name="date" value="{{ $dateInput }}">
    </div>
    <div class="col-md-3 custom-range-field {{ $rangeType === 'custom' ? '' : 'd-none' }}">
        <label class="form-label mb-1">Start Date</label>
        <input type="date" class="form-control" name="start_date" value="{{ $startDateInput }}">
    </div>
    <div class="col-md-3 custom-range-field {{ $rangeType === 'custom' ? '' : 'd-none' }}">
        <label class="form-label mb-1">End Date</label>
        <input type="date" class="form-control" name="end_date" value="{{ $endDateInput }}">
    </div>
    <div class="col-md-1 d-grid">
        <button type="submit" class="btn btn-primary w-100">Apply</button>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Period Cash In</div>
                <div class="h5 mb-0">Rs.{{ number_format($cashIn, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Period Cash Out</div>
                <div class="h5 mb-0">Rs.{{ number_format($cashOut, 2) }}</div>
                <div class="small text-muted mt-1">
                    Expenses: Rs.{{ number_format($expenseOut, 2) }} |
                    Purchase Pay: Rs.{{ number_format($purchaseOut, 2) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Period Net Cash</div>
                <div class="h5 mb-0 {{ $netCash >= 0 ? 'text-success' : 'text-danger' }}">
                    Rs.{{ number_format($netCash, 2) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Supplier Due</div>
                <div class="h5 mb-0">Rs.{{ number_format($payableDue, 2) }}</div>
                <div class="small text-muted mt-1">Open Bills: {{ $openBillsCount }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Today Collections by Payment</div>
            <div class="card-body">
                @if($salesByPayment->isEmpty())
                    <p class="mb-0 text-muted">No collections for selected range.</p>
                @else
                    <ul class="mb-0">
                        @foreach($salesByPayment as $mode => $amount)
                            <li><strong>{{ strtoupper($mode) }}:</strong> Rs.{{ number_format($amount, 2) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Quick P&L (Selected Range)</div>
            <div class="card-body">
                <p class="mb-1"><strong>Sales:</strong> Rs.{{ number_format($sales, 2) }}</p>
                <p class="mb-1"><strong>COGS:</strong> Rs.{{ number_format($cogs, 2) }}</p>
                <p class="mb-1"><strong>Expenses:</strong> Rs.{{ number_format($expenses, 2) }}</p>
                <p class="mb-0">
                    <strong>Net Profit:</strong>
                    <span class="{{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}">
                        Rs.{{ number_format($netProfit, 2) }}
                    </span>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Period Comparison</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Current</th>
                        <th>Previous</th>
                        <th>Delta</th>
                        <th>Delta %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($comparisonRows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>Rs.{{ number_format($row['current'], 2) }}</td>
                            <td>Rs.{{ number_format($row['previous'], 2) }}</td>
                            <td class="{{ $row['delta'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $row['delta'] >= 0 ? '+' : '' }}Rs.{{ number_format($row['delta'], 2) }}
                            </td>
                            <td class="{{ ($row['deltaPercent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                @if($row['deltaPercent'] === null)
                                    N/A
                                @else
                                    {{ $row['deltaPercent'] >= 0 ? '+' : '' }}{{ number_format($row['deltaPercent'], 2) }}%
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Cash Trend</div>
            <div class="card-body">
                <canvas id="cashTrendChart" height="110"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Payable Trend</div>
            <div class="card-body">
                <canvas id="payableTrendChart" height="110"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Top Supplier Dues</div>
    <div class="card-body p-0">
        @if($payablesBySupplier->isEmpty())
            <div class="p-3 text-muted">No outstanding supplier dues.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>Open Bills</th>
                            <th>Total Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payablesBySupplier as $row)
                            <tr>
                                <td>{{ $row->supplier_name ?: 'Unknown' }}</td>
                                <td>{{ $row->open_bills }}</td>
                                <td>Rs.{{ number_format((float) $row->due_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('purchases.index') }}" class="btn btn-sm btn-outline-primary">Go to Purchases</a>
    <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-outline-primary">Go to Expenses</a>
    <a href="{{ route('reports.suppliers.ledger') }}" class="btn btn-sm btn-outline-primary">Supplier Ledger</a>
    <a href="{{ route('reports.profit_loss.monthly') }}" class="btn btn-sm btn-outline-primary">Monthly P&L</a>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(() => {
    const rangeSelect = document.getElementById('range_type');
    const customFields = document.querySelectorAll('.custom-range-field');

    const toggleCustomRange = () => {
        const show = rangeSelect && rangeSelect.value === 'custom';
        customFields.forEach((node) => node.classList.toggle('d-none', !show));
    };

    if (rangeSelect) {
        rangeSelect.addEventListener('change', toggleCustomRange);
        toggleCustomRange();
    }

    if (typeof Chart === 'undefined') {
        return;
    }

    const cashTrend = @json($cashTrend);
    const payableTrend = @json($payableTrend);

    const cashCtx = document.getElementById('cashTrendChart');
    if (cashCtx) {
        new Chart(cashCtx, {
            type: 'line',
            data: {
                labels: cashTrend.labels,
                datasets: [
                    {
                        label: 'Cash In',
                        data: cashTrend.cashIn,
                        borderColor: '#1d7d49',
                        backgroundColor: 'rgba(29, 125, 73, 0.12)',
                        borderWidth: 2,
                        tension: 0.35
                    },
                    {
                        label: 'Cash Out',
                        data: cashTrend.cashOut,
                        borderColor: '#b02a37',
                        backgroundColor: 'rgba(176, 42, 55, 0.12)',
                        borderWidth: 2,
                        tension: 0.35
                    },
                    {
                        label: 'Net Cash',
                        data: cashTrend.netCash,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.12)',
                        borderWidth: 2,
                        tension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    const payableCtx = document.getElementById('payableTrendChart');
    if (payableCtx) {
        new Chart(payableCtx, {
            type: 'bar',
            data: {
                labels: payableTrend.labels,
                datasets: [
                    {
                        label: 'Outstanding Due',
                        data: payableTrend.due,
                        backgroundColor: 'rgba(255, 159, 64, 0.7)',
                        borderColor: '#fd7e14',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
})();
</script>
@endsection

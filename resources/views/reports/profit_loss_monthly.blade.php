@extends('layouts.app')

@section('content')
<h2>Monthly Profit and Loss</h2>

<form method="GET" action="{{ route('reports.profit_loss.monthly') }}">
    <label>Month</label>
    <input type="month" name="month" value="{{ $month }}">
    <button type="submit">View</button>
</form>

<hr>

<h3>Summary ({{ $month }})</h3>
<p><strong>Total Sales:</strong> Rs.{{ number_format($totalSales, 2) }}</p>
<p><strong>Total COGS:</strong> Rs.{{ number_format($totalCogs, 2) }}</p>
<p><strong>Gross Profit:</strong> Rs.{{ number_format($grossProfit, 2) }}</p>
<p><strong>Total Expenses:</strong> Rs.{{ number_format($totalExpense, 2) }}</p>
<p>
    <strong>Net Profit:</strong>
    <span style="color: {{ $netProfit >= 0 ? 'green' : 'red' }};">
        Rs.{{ number_format($netProfit, 2) }}
    </span>
</p>

<hr>

<h4>Sales by Payment Mode</h4>
<ul>
    @forelse($salesByPayment as $mode => $amount)
        <li><strong>{{ strtoupper($mode) }}:</strong> Rs.{{ number_format($amount, 2) }}</li>
    @empty
        <li>No sales in this month.</li>
    @endforelse
</ul>

<h4>Expenses by Category</h4>
<ul>
    @forelse($expensesByCategory as $category => $amount)
        <li><strong>{{ strtoupper($category) }}:</strong> Rs.{{ number_format($amount, 2) }}</li>
    @empty
        <li>No expenses in this month.</li>
    @endforelse
</ul>
@endsection

@extends('layouts.app')

@section('content')
<h2>Expenses</h2>

@if(session('success'))
    <p style="color:green">{{ session('success') }}</p>
@endif

@if($errors->any())
    <div style="color:red; margin-bottom: 10px;">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('expenses.store') }}" style="margin-bottom: 20px;">
    @csrf

    <label>Date</label>
    <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required>

    <label>Category</label>
    <select name="category" required>
        <option value="">Select category</option>
        @foreach($categories as $category)
            <option value="{{ $category }}" {{ old('category') === $category ? 'selected' : '' }}>
                {{ strtoupper($category) }}
            </option>
        @endforeach
    </select>

    <label>Amount</label>
    <input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required>

    <label>Notes</label>
    <input type="text" name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Optional notes">

    <button type="submit">Add Expense</button>
</form>

<form method="GET" action="{{ route('expenses.index') }}" style="margin-bottom: 16px;">
    <label>Month</label>
    <input type="month" name="month" value="{{ $month }}">
    <button type="submit">Filter</button>
</form>

<h3>Summary ({{ $month }})</h3>
<p><strong>Total Expense:</strong> Rs.{{ number_format($totalExpense, 2) }}</p>

<h4>By Category</h4>
<ul>
    @foreach($categories as $category)
        <li>
            <strong>{{ strtoupper($category) }}:</strong>
            Rs.{{ number_format($byCategory->get($category, 0), 2) }}
        </li>
    @endforeach
</ul>

<h3>Entries</h3>

@if($expenses->isEmpty())
    <p>No expenses for this month.</p>
@else
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $expense)
                <tr>
                    <td>{{ $expense->expense_date->format('Y-m-d') }}</td>
                    <td>{{ strtoupper($expense->category) }}</td>
                    <td>Rs.{{ number_format($expense->amount, 2) }}</td>
                    <td>{{ $expense->notes ?: '-' }}</td>
                    <td>
                        <a href="{{ route('expenses.edit', ['expense' => $expense->id, 'month' => $month]) }}">Edit</a>

                        <form method="POST" action="{{ route('expenses.destroy', $expense->id) }}" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="month" value="{{ $month }}">
                            <button type="submit" onclick="return confirm('Delete this expense?')">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@endsection

@extends('layouts.app')

@section('content')
<h2>Edit Expense</h2>

<p>
    <a href="{{ route('expenses.index', ['month' => $month]) }}">Back to Expenses</a>
</p>

@if($errors->any())
    <div style="color:red; margin-bottom: 10px;">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('expenses.update', $expense->id) }}">
    @csrf
    @method('PUT')

    <input type="hidden" name="month" value="{{ $month }}">

    <label>Date</label>
    <input type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required>

    <label>Category</label>
    <select name="category" required>
        <option value="">Select category</option>
        @foreach($categories as $category)
            <option value="{{ $category }}" {{ old('category', $expense->category) === $category ? 'selected' : '' }}>
                {{ strtoupper($category) }}
            </option>
        @endforeach
    </select>

    <label>Amount</label>
    <input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount', $expense->amount) }}" required>

    <label>Notes</label>
    <input type="text" name="notes" maxlength="500" value="{{ old('notes', $expense->notes) }}" placeholder="Optional notes">

    <button type="submit">Update Expense</button>
</form>
@endsection

@extends('layouts.app')

@section('content')
<h2>Supplier Ledger and Due Aging</h2>

<form method="GET" action="{{ route('reports.suppliers.ledger') }}">
    <label>As of Date</label>
    <input type="date" name="as_of" value="{{ $asOf }}">
    <button type="submit">View</button>
</form>

<hr>

<h3>Summary (As of {{ $asOf }})</h3>
<p><strong>Total Purchase:</strong> Rs.{{ number_format($grand['total_purchase'], 2) }}</p>
<p><strong>Total Paid:</strong> Rs.{{ number_format($grand['total_paid'], 2) }}</p>
<p><strong>Total Due:</strong> Rs.{{ number_format($grand['total_due'], 2) }}</p>
<p><strong>Open Bills:</strong> {{ $grand['open_bills'] }}</p>

<h4>Due Aging Summary</h4>
<p><strong>0-30 Days:</strong> Rs.{{ number_format($grand['bucket_0_30'], 2) }}</p>
<p><strong>31-60 Days:</strong> Rs.{{ number_format($grand['bucket_31_60'], 2) }}</p>
<p><strong>60+ Days:</strong> Rs.{{ number_format($grand['bucket_60_plus'], 2) }}</p>

<hr>

<h3>Supplier-wise Ledger</h3>

@if($rows->isEmpty())
    <p>No purchase data found up to this date.</p>
@else
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Supplier</th>
                <th>Total Purchase</th>
                <th>Total Paid</th>
                <th>Total Due</th>
                <th>Open Bills</th>
                <th>0-30</th>
                <th>31-60</th>
                <th>60+</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>
                        @if($row['supplier_id'])
                            <a href="{{ route('reports.suppliers.ledger.show', ['supplier' => $row['supplier_id'], 'as_of' => $asOf]) }}">
                                {{ $row['supplier_name'] }}
                            </a>
                        @else
                            {{ $row['supplier_name'] }}
                        @endif
                    </td>
                    <td>Rs.{{ number_format($row['total_purchase'], 2) }}</td>
                    <td>Rs.{{ number_format($row['total_paid'], 2) }}</td>
                    <td>Rs.{{ number_format($row['total_due'], 2) }}</td>
                    <td>{{ $row['open_bills'] }}</td>
                    <td>Rs.{{ number_format($row['bucket_0_30'], 2) }}</td>
                    <td>Rs.{{ number_format($row['bucket_31_60'], 2) }}</td>
                    <td>Rs.{{ number_format($row['bucket_60_plus'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@endsection

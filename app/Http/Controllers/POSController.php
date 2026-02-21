<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\BillingService;
use Illuminate\Http\Request;

class POSController extends Controller
{
    public function index()
    {
        $products = Product::where('type', 'finished_good')
            ->where('is_active', true)
            ->get();

        return view('pos.index', compact('products'));
    }

    public function checkout(Request $request)
    {
                $request->validate([
    'items' => 'required|array',
    'payment_mode' => 'required|in:cash,upi,card',
]);

        $billing = new BillingService();
        $billing->createSale($request->items, $request->payment_mode);

        return redirect()->back()->with('success', 'Sale completed');
    }
}

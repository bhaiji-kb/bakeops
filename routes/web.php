<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\InventoryController;

Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::post('/inventory/add', [InventoryController::class, 'addStock'])->name('inventory.add');
Route::post('/inventory/waste', [InventoryController::class, 'recordWastage'])->name('inventory.waste');

use App\Http\Controllers\SalesReportController;

Route::get('/reports/sales', [SalesReportController::class, 'daily'])->name('reports.sales.daily');


use App\Http\Controllers\POSController;

Route::get('/pos', [POSController::class, 'index']);
Route::post('/pos/checkout', [POSController::class, 'checkout'])->name('pos.checkout');
Route::get('/', function () {
    return view('welcome');
});


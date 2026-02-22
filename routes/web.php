<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AccountsDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelWebhookController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\IntegrationConnectorController;
use App\Http\Controllers\OnlineOrderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    $user = auth()->user();
    if ($user && $user->role === 'owner') {
        return redirect()->route('accounts.dashboard');
    }

    if ($user && in_array($user->role, ['manager', 'cashier'], true)) {
        return redirect()->route('orders.index');
    }

    return redirect()->route('purchases.index');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/integrations/webhooks/{channel}', [ChannelWebhookController::class, 'receive'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('integrations.webhook.receive');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware('role:owner,manager,cashier')->group(function () {
        // Operations: Online Queue
        Route::get('/pos/online-orders', [OnlineOrderController::class, 'index'])->name('pos.online_orders.index');
        Route::post('/pos/online-orders/{channelOrder}/accept', [OnlineOrderController::class, 'accept'])->name('pos.online_orders.accept');
        Route::post('/pos/online-orders/{channelOrder}/reject', [OnlineOrderController::class, 'reject'])->name('pos.online_orders.reject');
        Route::post('/pos/online-orders/{channelOrder}/ready', [OnlineOrderController::class, 'ready'])->name('pos.online_orders.ready');

        // Operations: Orders + KOT
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('/orders/{order}/kot', [OrderController::class, 'printKot'])->name('orders.kot.print');

        // Operations: POS APIs (lookup + checkout)
        Route::get('/pos', [POSController::class, 'index'])->name('pos.index');
        Route::get('/pos/products/search', [POSController::class, 'productSearch'])->name('pos.products.search');
        Route::get('/pos/products/lookup', [POSController::class, 'productByCode'])->name('pos.products.lookup');
        Route::get('/pos/customers/lookup', [POSController::class, 'customerLookup'])->name('pos.customers.lookup');
        Route::post('/pos/checkout', [POSController::class, 'checkout'])->name('pos.checkout');

        // Operations: Sales
        Route::get('/sales', [POSController::class, 'salesHistory'])->name('pos.sales.index');
        Route::get('/sales/{sale}/invoice', [POSController::class, 'invoice'])->name('pos.sales.invoice');
        Route::get('/sales/{sale}/invoice/pdf', [POSController::class, 'invoicePdf'])->name('pos.sales.invoice.pdf');

        // Operations: Customers
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    Route::middleware('role:owner,manager,purchase')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
        Route::post('/recipes/import', [RecipeController::class, 'import'])->name('recipes.import');
        Route::get('/recipes/{product}/edit', [RecipeController::class, 'edit'])->name('recipes.edit');
        Route::put('/recipes/{product}', [RecipeController::class, 'update'])->name('recipes.update');
        Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
        Route::post('/production', [ProductionController::class, 'store'])->name('production.store');

        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/add', [InventoryController::class, 'addStock'])->name('inventory.add');
        Route::post('/inventory/waste', [InventoryController::class, 'recordWastage'])->name('inventory.waste');

        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::post('/purchases/{purchase}/payments', [PurchaseController::class, 'addPayment'])->name('purchases.payments.store');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

        Route::get('/reports/suppliers/ledger', [SupplierReportController::class, 'ledger'])->name('reports.suppliers.ledger');
        Route::get('/reports/suppliers/ledger/{supplier}', [SupplierReportController::class, 'ledgerSupplier'])->name('reports.suppliers.ledger.show');
    });

    Route::middleware('role:owner,manager')->group(function () {
        Route::get('/accounts/dashboard', [AccountsDashboardController::class, 'index'])->name('accounts.dashboard');
        Route::get('/accounts/dashboard/export/pdf', [AccountsDashboardController::class, 'exportPdf'])->name('accounts.dashboard.export.pdf');
        Route::get('/accounts/dashboard/export/excel', [AccountsDashboardController::class, 'exportExcel'])->name('accounts.dashboard.export.excel');

        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        Route::get('/reports/sales', [SalesReportController::class, 'daily'])->name('reports.sales.daily');
        Route::get('/reports/profit-loss', [FinanceReportController::class, 'monthly'])->name('reports.profit_loss.monthly');

        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('logs.index');
    });

    Route::middleware('role:owner')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset_password');

        Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::put('/admin/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');

        Route::get('/admin/connectors', [IntegrationConnectorController::class, 'index'])->name('integrations.connectors.index');
        Route::post('/admin/connectors', [IntegrationConnectorController::class, 'store'])->name('integrations.connectors.store');
        Route::get('/admin/connectors/{connector}/edit', [IntegrationConnectorController::class, 'edit'])->name('integrations.connectors.edit');
        Route::put('/admin/connectors/{connector}', [IntegrationConnectorController::class, 'update'])->name('integrations.connectors.update');
    });
});

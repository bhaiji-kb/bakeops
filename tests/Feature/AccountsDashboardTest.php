<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_accounts_dashboard_and_view_metrics(): void
    {
        Carbon::setTestNow('2026-02-21 10:00:00');
        try {
            $manager = User::factory()->create([
                'role' => User::ROLE_MANAGER,
                'is_active' => true,
            ]);
            $supplier = Supplier::create([
                'name' => 'Sunrise Supplies',
                'is_active' => true,
            ]);
            $finished = Product::create([
                'name' => 'Paneer Puff',
                'type' => 'finished_good',
                'unit' => 'pcs',
                'reorder_level' => 1,
                'price' => 40,
                'is_active' => true,
            ]);

            $sale = Sale::create([
                'bill_number' => 'INV-20260221-00001',
                'sub_total' => 500,
                'discount_amount' => 20,
                'tax_amount' => 10,
                'round_off' => 0,
                'total_amount' => 490,
                'payment_mode' => 'cash',
                'paid_amount' => 450,
                'balance_amount' => 40,
            ]);
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $finished->id,
                'quantity' => 10,
                'price' => 49,
                'unit_cost' => 20,
                'total' => 490,
                'cost_total' => 200,
            ]);

            Expense::create([
                'expense_date' => '2026-02-21',
                'category' => 'rent',
                'amount' => 120,
                'notes' => 'Rent',
            ]);

            $purchase = Purchase::create([
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'bill_number' => 'BILL-1',
                'purchase_date' => '2026-02-20',
                'total_amount' => 400,
                'paid_amount' => 100,
                'due_amount' => 300,
                'status' => 'partial',
            ]);
            PurchasePayment::create([
                'purchase_id' => $purchase->id,
                'payment_date' => '2026-02-21',
                'amount' => 80,
                'payment_mode' => 'cash',
                'notes' => 'Part payment',
            ]);

            $response = $this->actingAs($manager)->get(route('accounts.dashboard', [
                'date' => '2026-02-21',
            ]));

            $response->assertOk();
            $response->assertViewHas('todayIn', fn ($v) => abs((float) $v - 450.0) < 0.01);
            $response->assertViewHas('todayExpenseOut', fn ($v) => abs((float) $v - 120.0) < 0.01);
            $response->assertViewHas('todayPurchaseOut', fn ($v) => abs((float) $v - 80.0) < 0.01);
            $response->assertViewHas('todayOut', fn ($v) => abs((float) $v - 200.0) < 0.01);
            $response->assertViewHas('todayNet', fn ($v) => abs((float) $v - 250.0) < 0.01);
            $response->assertViewHas('totalPayableDue', fn ($v) => abs((float) $v - 300.0) < 0.01);
            $response->assertViewHas('mtdSales', fn ($v) => abs((float) $v - 490.0) < 0.01);
            $response->assertViewHas('mtdCogs', fn ($v) => abs((float) $v - 200.0) < 0.01);
            $response->assertViewHas('mtdExpenses', fn ($v) => abs((float) $v - 120.0) < 0.01);
            $response->assertViewHas('mtdNetProfit', fn ($v) => abs((float) $v - 170.0) < 0.01);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manager_can_use_week_range_with_comparison_and_trends(): void
    {
        Carbon::setTestNow('2026-02-21 10:00:00');
        try {
            $manager = User::factory()->create([
                'role' => User::ROLE_MANAGER,
                'is_active' => true,
            ]);

            $supplier = Supplier::create([
                'name' => 'Weekly Vendor',
                'is_active' => true,
            ]);

            $product = Product::create([
                'name' => 'Khari',
                'type' => 'finished_good',
                'unit' => 'pcs',
                'reorder_level' => 1,
                'price' => 30,
                'is_active' => true,
            ]);

            $currentSale = Sale::create([
                'bill_number' => 'INV-WEEK-CURR',
                'sub_total' => 220,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'round_off' => 0,
                'total_amount' => 220,
                'payment_mode' => 'cash',
                'paid_amount' => 200,
                'balance_amount' => 20,
            ]);
            DB::table('sales')->where('id', $currentSale->id)->update([
                'created_at' => '2026-02-17 09:00:00',
                'updated_at' => '2026-02-17 09:00:00',
            ]);
            SaleItem::create([
                'sale_id' => $currentSale->id,
                'product_id' => $product->id,
                'quantity' => 4,
                'price' => 55,
                'unit_cost' => 20,
                'total' => 220,
                'cost_total' => 80,
            ]);

            $previousSale = Sale::create([
                'bill_number' => 'INV-WEEK-PREV',
                'sub_total' => 120,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'round_off' => 0,
                'total_amount' => 120,
                'payment_mode' => 'cash',
                'paid_amount' => 100,
                'balance_amount' => 20,
            ]);
            DB::table('sales')->where('id', $previousSale->id)->update([
                'created_at' => '2026-02-10 09:00:00',
                'updated_at' => '2026-02-10 09:00:00',
            ]);
            SaleItem::create([
                'sale_id' => $previousSale->id,
                'product_id' => $product->id,
                'quantity' => 3,
                'price' => 40,
                'unit_cost' => 20,
                'total' => 120,
                'cost_total' => 60,
            ]);

            Expense::create([
                'expense_date' => '2026-02-18',
                'category' => 'salary',
                'amount' => 50,
                'notes' => 'Current week salary',
            ]);
            Expense::create([
                'expense_date' => '2026-02-12',
                'category' => 'salary',
                'amount' => 30,
                'notes' => 'Previous week salary',
            ]);

            $purchaseCurrent = Purchase::create([
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'bill_number' => 'B-CURR',
                'purchase_date' => '2026-02-17',
                'total_amount' => 200,
                'paid_amount' => 0,
                'due_amount' => 200,
                'status' => 'unpaid',
            ]);
            PurchasePayment::create([
                'purchase_id' => $purchaseCurrent->id,
                'payment_date' => '2026-02-19',
                'amount' => 20,
                'payment_mode' => 'cash',
            ]);

            $purchasePrevious = Purchase::create([
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'bill_number' => 'B-PREV',
                'purchase_date' => '2026-02-10',
                'total_amount' => 100,
                'paid_amount' => 0,
                'due_amount' => 100,
                'status' => 'unpaid',
            ]);
            PurchasePayment::create([
                'purchase_id' => $purchasePrevious->id,
                'payment_date' => '2026-02-11',
                'amount' => 10,
                'payment_mode' => 'cash',
            ]);

            $response = $this->actingAs($manager)->get(route('accounts.dashboard', [
                'range_type' => 'week',
                'date' => '2026-02-21',
            ]));

            $response->assertOk();
            $response->assertViewHas('periodLabel', '2026-02-16 to 2026-02-22');
            $response->assertViewHas('previousPeriodLabel', '2026-02-09 to 2026-02-15');
            $response->assertViewHas('cashIn', fn ($v) => abs((float) $v - 200.0) < 0.01);
            $response->assertViewHas('cashOut', fn ($v) => abs((float) $v - 70.0) < 0.01);
            $response->assertViewHas('comparisonRows', function (array $rows) {
                foreach ($rows as $row) {
                    if ($row['label'] === 'Cash In') {
                        return abs((float) $row['current'] - 200.0) < 0.01
                            && abs((float) $row['previous'] - 100.0) < 0.01
                            && abs((float) $row['delta'] - 100.0) < 0.01;
                    }
                }

                return false;
            });
            $response->assertViewHas('cashTrend', function (array $trend) {
                return count($trend['labels']) === 7
                    && count($trend['cashIn']) === 7
                    && count($trend['cashOut']) === 7
                    && count($trend['netCash']) === 7;
            });
            $response->assertViewHas('payableTrend', fn (array $trend) => count($trend['due']) === 7);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manager_can_export_accounts_dashboard_pdf_and_excel(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $pdfResponse = $this->actingAs($manager)->get(route('accounts.dashboard.export.pdf', [
            'range_type' => 'day',
            'date' => '2026-02-21',
        ]));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower((string) $pdfResponse->headers->get('content-type')));
        $this->assertStringContainsString('attachment;', strtolower((string) $pdfResponse->headers->get('content-disposition')));

        $csvResponse = $this->actingAs($manager)->get(route('accounts.dashboard.export.excel', [
            'range_type' => 'day',
            'date' => '2026-02-21',
        ]));
        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', strtolower((string) $csvResponse->headers->get('content-type')));
        $this->assertStringContainsString('attachment;', strtolower((string) $csvResponse->headers->get('content-disposition')));
        $this->assertStringContainsString('BakeOps Accounts Dashboard Snapshot', $csvResponse->streamedContent());
    }

    public function test_purchase_role_cannot_open_accounts_dashboard(): void
    {
        $purchaseUser = User::factory()->create([
            'role' => User::ROLE_PURCHASE,
            'is_active' => true,
        ]);

        $this->actingAs($purchaseUser)->get(route('accounts.dashboard'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('accounts.dashboard.export.pdf'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('accounts.dashboard.export.excel'))->assertForbidden();
    }
}

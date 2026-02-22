<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_checkout_creates_sale_updates_stock_and_logs_activity(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = Product::create([
            'name' => 'Chocolate Cake',
            'type' => 'finished_good',
            'price' => 120,
            'is_active' => true,
        ]);

        InventoryTransaction::create([
            'product_id' => $product->id,
            'quantity' => 10,
            'transaction_type' => 'IN',
            'reference_type' => 'seed',
            'reference_id' => 1,
            'notes' => 'Initial stock',
        ]);

        $response = $this->actingAs($cashier)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'payment_mode' => 'cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Sale completed');

        $sale = Sale::query()->first();
        $this->assertNotNull($sale);
        $this->assertSame('cash', $sale->payment_mode);
        $this->assertEquals(240.0, (float) $sale->sub_total);
        $this->assertEquals(0.0, (float) $sale->discount_amount);
        $this->assertEquals(0.0, (float) $sale->tax_amount);
        $this->assertEquals(0.0, (float) $sale->round_off);
        $this->assertEquals(240.0, (float) $sale->total_amount);
        $this->assertEquals(240.0, (float) $sale->paid_amount);
        $this->assertEquals(0.0, (float) $sale->balance_amount);

        $saleItem = SaleItem::query()->where('sale_id', $sale->id)->first();
        $this->assertNotNull($saleItem);
        $this->assertSame($product->id, (int) $saleItem->product_id);
        $this->assertEquals(2.0, (float) $saleItem->quantity);
        $this->assertEquals(120.0, (float) $saleItem->price);
        $this->assertEquals(0.0, (float) $saleItem->unit_cost);
        $this->assertEquals(240.0, (float) $saleItem->total);
        $this->assertEquals(0.0, (float) $saleItem->cost_total);

        $outTxn = InventoryTransaction::query()
            ->where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->where('transaction_type', 'OUT')
            ->first();
        $this->assertNotNull($outTxn);
        $this->assertEquals(2.0, (float) $outTxn->quantity);

        $this->assertEquals(8.0, (float) $product->fresh()->currentStock());

        $log = ActivityLog::query()
            ->where('module', 'pos')
            ->where('action', 'checkout')
            ->where('entity_type', Sale::class)
            ->where('entity_id', $sale->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame($cashier->id, (int) $log->user_id);
    }

    public function test_purchase_bill_and_due_payment_update_financials_stock_and_logs(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);
        $supplier = Supplier::create([
            'name' => 'Fresh Mills',
            'phone' => '9999999999',
            'address' => 'Market Road',
            'is_active' => true,
        ]);
        $ingredient = Product::create([
            'name' => 'Flour',
            'type' => 'raw_material',
            'price' => 0,
            'is_active' => true,
        ]);

        $createResponse = $this->actingAs($purchaseUser)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'bill_number' => 'SUP-1001',
            'purchase_date' => '2026-02-20',
            'notes' => 'Monthly ingredient purchase',
            'initial_paid_amount' => 40,
            'initial_payment_mode' => 'cash',
            'items' => [
                [
                    'product_id' => $ingredient->id,
                    'quantity' => 5,
                    'unit_price' => 20,
                ],
            ],
        ]);

        $purchase = Purchase::query()->first();
        $this->assertNotNull($purchase);

        $createResponse->assertRedirect(route('purchases.show', ['purchase' => $purchase->id]));
        $this->assertSame($supplier->id, (int) $purchase->supplier_id);
        $this->assertSame('SUP-1001', $purchase->bill_number);
        $this->assertEquals(100.0, (float) $purchase->total_amount);
        $this->assertEquals(40.0, (float) $purchase->paid_amount);
        $this->assertEquals(60.0, (float) $purchase->due_amount);
        $this->assertSame('partial', $purchase->status);

        $item = PurchaseItem::query()->where('purchase_id', $purchase->id)->first();
        $this->assertNotNull($item);
        $this->assertSame($ingredient->id, (int) $item->product_id);
        $this->assertEquals(5.0, (float) $item->quantity);
        $this->assertEquals(20.0, (float) $item->unit_price);
        $this->assertEquals(100.0, (float) $item->total);

        $inwardTxn = InventoryTransaction::query()
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->where('transaction_type', 'IN')
            ->first();
        $this->assertNotNull($inwardTxn);
        $this->assertEquals(5.0, (float) $inwardTxn->quantity);

        $createLog = ActivityLog::query()
            ->where('module', 'purchases')
            ->where('action', 'create_bill')
            ->where('entity_type', Purchase::class)
            ->where('entity_id', $purchase->id)
            ->first();
        $this->assertNotNull($createLog);
        $this->assertSame($purchaseUser->id, (int) $createLog->user_id);

        $paymentResponse = $this->actingAs($purchaseUser)->post(route('purchases.payments.store', ['purchase' => $purchase->id]), [
            'payment_date' => '2026-02-21',
            'amount' => 60,
            'payment_mode' => 'bank',
            'notes' => 'Final settlement',
        ]);

        $paymentResponse->assertRedirect(route('purchases.show', ['purchase' => $purchase->id]));
        $purchase->refresh();
        $this->assertEquals(100.0, (float) $purchase->paid_amount);
        $this->assertEquals(0.0, (float) $purchase->due_amount);
        $this->assertSame('paid', $purchase->status);

        $this->assertSame(2, PurchasePayment::query()->where('purchase_id', $purchase->id)->count());
        $payment = PurchasePayment::query()
            ->where('purchase_id', $purchase->id)
            ->where('amount', 60)
            ->latest('id')
            ->first();
        $this->assertNotNull($payment);

        $paymentLog = ActivityLog::query()
            ->where('module', 'purchases')
            ->where('action', 'add_payment')
            ->where('entity_type', PurchasePayment::class)
            ->where('entity_id', $payment->id)
            ->first();
        $this->assertNotNull($paymentLog);
        $this->assertSame($purchaseUser->id, (int) $paymentLog->user_id);
    }

    public function test_expense_update_and_delete_are_persisted_and_logged(): void
    {
        $manager = $this->createUser(User::ROLE_MANAGER);
        $expense = Expense::create([
            'expense_date' => '2026-02-01',
            'category' => 'rent',
            'amount' => 5000,
            'notes' => 'Office rent',
        ]);

        $updateResponse = $this->actingAs($manager)->put(route('expenses.update', ['expense' => $expense->id]), [
            'expense_date' => '2026-02-10',
            'category' => 'salary',
            'amount' => 7500,
            'notes' => 'Payroll adjusted',
            'month' => '2026-02',
        ]);

        $updateResponse->assertRedirect(route('expenses.index', ['month' => '2026-02']));
        $expense->refresh();
        $this->assertSame('salary', $expense->category);
        $this->assertEquals(7500.0, (float) $expense->amount);
        $this->assertSame('Payroll adjusted', $expense->notes);

        $updateLog = ActivityLog::query()
            ->where('module', 'expenses')
            ->where('action', 'update')
            ->where('entity_type', Expense::class)
            ->where('entity_id', $expense->id)
            ->first();
        $this->assertNotNull($updateLog);
        $this->assertSame($manager->id, (int) $updateLog->user_id);

        $deleteResponse = $this->actingAs($manager)->delete(route('expenses.destroy', ['expense' => $expense->id]), [
            'month' => '2026-02',
        ]);

        $deleteResponse->assertRedirect(route('expenses.index', ['month' => '2026-02']));
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);

        $deleteLog = ActivityLog::query()
            ->where('module', 'expenses')
            ->where('action', 'delete')
            ->where('entity_type', Expense::class)
            ->where('entity_id', $expense->id)
            ->first();
        $this->assertNotNull($deleteLog);
        $this->assertSame($manager->id, (int) $deleteLog->user_id);
    }

    private function createUser(string $role, bool $isActive = true): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => $isActive,
        ]);
    }
}

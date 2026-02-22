<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegativeFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_checkout_with_insufficient_stock_returns_error_without_creating_sale(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = Product::create([
            'name' => 'Black Forest',
            'type' => 'finished_good',
            'price' => 300,
            'is_active' => true,
        ]);

        InventoryTransaction::create([
            'product_id' => $product->id,
            'quantity' => 1,
            'transaction_type' => 'IN',
            'reference_type' => 'seed',
            'reference_id' => 1,
            'notes' => 'Initial stock',
        ]);

        $response = $this->actingAs($cashier)->from(route('pos.index'))->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'payment_mode' => 'cash',
        ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHasErrors([
            'checkout' => "Insufficient stock for {$product->name}",
        ]);

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertSame(1, InventoryTransaction::query()->where('product_id', $product->id)->count());
        $this->assertDatabaseMissing('activity_logs', [
            'module' => 'pos',
            'action' => 'checkout',
        ]);
    }

    public function test_purchase_payment_cannot_exceed_due_amount(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);
        $supplier = Supplier::create([
            'name' => 'Daily Farms',
            'is_active' => true,
        ]);
        $ingredient = Product::create([
            'name' => 'Butter',
            'type' => 'raw_material',
            'price' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($purchaseUser)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-02-21',
            'initial_paid_amount' => 0,
            'items' => [
                [
                    'product_id' => $ingredient->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $purchase = Purchase::query()->first();
        $this->assertNotNull($purchase);
        $this->assertEquals(100.0, (float) $purchase->due_amount);

        $response = $this->actingAs($purchaseUser)
            ->from(route('purchases.show', ['purchase' => $purchase->id]))
            ->post(route('purchases.payments.store', ['purchase' => $purchase->id]), [
                'payment_date' => '2026-02-21',
                'amount' => 150,
                'payment_mode' => 'bank',
                'notes' => 'Attempt overpay',
            ]);

        $response->assertRedirect(route('purchases.show', ['purchase' => $purchase->id]));
        $response->assertSessionHasErrors([
            'payment' => 'Payment amount cannot exceed pending due amount.',
        ]);

        $purchase->refresh();
        $this->assertEquals(0.0, (float) $purchase->paid_amount);
        $this->assertEquals(100.0, (float) $purchase->due_amount);
        $this->assertSame('unpaid', $purchase->status);
        $this->assertDatabaseCount('purchase_payments', 0);
        $this->assertNull(
            ActivityLog::query()
                ->where('module', 'purchases')
                ->where('action', 'add_payment')
                ->first()
        );
    }

    public function test_purchase_creation_requires_complete_item_values(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);
        $supplier = Supplier::create([
            'name' => 'Bulk Traders',
            'is_active' => true,
        ]);
        $ingredient = Product::create([
            'name' => 'Sugar',
            'type' => 'raw_material',
            'price' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($purchaseUser)
            ->from(route('purchases.index'))
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'purchase_date' => '2026-02-21',
                'items' => [
                    [
                        'product_id' => $ingredient->id,
                        'quantity' => 10,
                        'unit_price' => null,
                    ],
                ],
            ]);

        $response->assertRedirect(route('purchases.index'));
        $response->assertSessionHasErrors([
            'items' => 'For each selected ingredient, fill both quantity and unit price.',
        ]);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('purchase_items', 0);
    }

    private function createUser(string $role, bool $isActive = true): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => $isActive,
        ]);
    }
}


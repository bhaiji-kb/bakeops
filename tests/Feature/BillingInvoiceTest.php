<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_generates_invoice_number_and_invoice_page(): void
    {
        BusinessSetting::create([
            'gst_enabled' => true,
            'gstin' => '22AAAAA0000A1Z5',
        ]);

        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Blueberry Pastry', 10, 80);

        $response = $this->actingAs($cashier)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'payment_mode' => 'cash',
            'discount_amount' => 10,
            'tax_amount' => 5,
            'round_off' => -0.50,
            'paid_amount' => 120,
        ]);

        $sale = Sale::query()->first();
        $this->assertNotNull($sale);
        $this->assertMatchesRegularExpression('/^INV-\d{8}-\d{5}$/', $sale->bill_number);
        $this->assertEquals(160.0, (float) $sale->sub_total);
        $this->assertEquals(10.0, (float) $sale->discount_amount);
        $this->assertEquals(5.0, (float) $sale->tax_amount);
        $this->assertEquals(-0.5, (float) $sale->round_off);
        $this->assertEquals(154.5, (float) $sale->total_amount);
        $this->assertEquals(120.0, (float) $sale->paid_amount);
        $this->assertEquals(34.5, (float) $sale->balance_amount);
        $this->assertSame($cashier->id, (int) $sale->created_by_user_id);

        $order = Order::query()->where('sale_id', $sale->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('outlet', $order->source);
        $this->assertSame('invoiced', $order->status);
        $this->assertNotNull($order->invoiced_at);
        $this->assertDatabaseHas('kots', [
            'order_id' => $order->id,
            'status' => 'closed',
        ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHas('invoice_sale_id', $sale->id);

        $invoiceResponse = $this->actingAs($cashier)->get(route('pos.sales.invoice', ['sale' => $sale->id]));
        $invoiceResponse->assertOk();
        $invoiceResponse->assertSee($sale->bill_number);
        $invoiceResponse->assertSee('Blueberry Pastry');
        $invoiceResponse->assertSee('154.50');
        $invoiceResponse->assertSee('120.00');
        $invoiceResponse->assertSee('34.50');
        $invoiceResponse->assertSee($cashier->name);
    }

    public function test_checkout_ignores_tax_when_gst_is_disabled(): void
    {
        BusinessSetting::create([
            'gst_enabled' => false,
        ]);

        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('No GST Item', 10, 80);

        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_mode' => 'cash',
            'discount_amount' => 0,
            'tax_amount' => 18,
            'round_off' => 0,
            'paid_amount' => 80,
        ])->assertRedirect(route('pos.index'));

        $sale = Sale::query()->first();
        $this->assertNotNull($sale);
        $this->assertSame(0.0, (float) $sale->tax_amount);
        $this->assertSame(80.0, (float) $sale->total_amount);
    }

    public function test_sales_history_shows_recent_sales_and_invoice_action(): void
    {
        $manager = $this->createUser(User::ROLE_MANAGER);
        $product = $this->createFinishedProductWithStock('Brownie', 8, 60);

        $this->actingAs($manager)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_mode' => 'upi',
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'paid_amount' => 60,
        ]);

        $sale = Sale::query()->first();
        $this->assertNotNull($sale);

        $historyResponse = $this->actingAs($manager)->get(route('pos.sales.index', [
            'invoice' => $sale->bill_number,
        ]));

        $historyResponse->assertOk();
        $historyResponse->assertSee($sale->bill_number);
        $historyResponse->assertSee(route('pos.sales.invoice', ['sale' => $sale->id]), false);
    }

    public function test_purchase_role_cannot_access_billing_history_or_invoice(): void
    {
        $purchase = $this->createUser(User::ROLE_PURCHASE);
        $sale = Sale::create([
            'bill_number' => 'INV-20260221-00001',
            'total_amount' => 100,
            'payment_mode' => 'cash',
        ]);

        $this->actingAs($purchase)->get(route('pos.sales.index'))->assertForbidden();
        $this->actingAs($purchase)->get(route('pos.sales.invoice', ['sale' => $sale->id]))->assertForbidden();
        $this->actingAs($purchase)->get(route('pos.sales.invoice.pdf', ['sale' => $sale->id]))->assertForbidden();
    }

    public function test_cashier_can_download_invoice_pdf(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Red Velvet Slice', 10, 110);

        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_mode' => 'cash',
        ]);

        $sale = Sale::query()->first();
        $this->assertNotNull($sale);

        $response = $this->actingAs($cashier)->get(route('pos.sales.invoice.pdf', ['sale' => $sale->id]));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_paid_amount_cannot_exceed_final_total(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Test Item', 5, 50);

        $response = $this->actingAs($cashier)
            ->from(route('pos.index'))
            ->post(route('pos.checkout'), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'payment_mode' => 'cash',
                'paid_amount' => 100,
            ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHasErrors([
            'checkout' => 'Paid amount cannot exceed final total.',
        ]);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_cashier_can_lookup_product_by_code_for_pos_add_flow(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Cheese Puff', 12, 35, 'FG022', '22');

        $response = $this->actingAs($cashier)->getJson(route('pos.products.lookup', [
            'code' => 'FG022',
        ]));

        $response->assertOk();
        $response->assertJson([
            'id' => $product->id,
            'code' => 'FG022',
            'name' => 'Cheese Puff',
            'price' => 35.0,
            'unit' => 'pcs',
        ]);
    }

    public function test_cashier_can_search_products_for_pos_suggestions(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $this->createFinishedProductWithStock('Swiss Cake', 8, 120, 'FG002', '02');
        $this->createFinishedProductWithStock('Swiss Roll', 5, 95, 'FG003', '03');

        $response = $this->actingAs($cashier)->getJson(route('pos.products.search', [
            'q' => 'swi',
        ]));

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['code' => 'FG002', 'name' => 'Swiss Cake']);
        $response->assertJsonFragment(['code' => 'FG003', 'name' => 'Swiss Roll']);
    }

    public function test_cashier_can_lookup_by_legacy_two_digit_code_for_backward_compatibility(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Legacy Swiss Cake', 8, 120, 'FG012', '12');

        $response = $this->actingAs($cashier)->getJson(route('pos.products.lookup', [
            'code' => '12',
        ]));

        $response->assertOk();
        $response->assertJson([
            'id' => $product->id,
            'code' => 'FG012',
            'legacy_code' => '12',
            'name' => 'Legacy Swiss Cake',
        ]);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createFinishedProductWithStock(
        string $name,
        float $stockQty,
        float $price,
        ?string $code = null,
        ?string $legacyCode = null
    ): Product
    {
        $product = Product::create([
            'code' => $code ?: 'FG' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'legacy_code' => $legacyCode,
            'name' => $name,
            'type' => 'finished_good',
            'unit' => 'pcs',
            'reorder_level' => 1,
            'price' => $price,
            'is_active' => true,
        ]);

        InventoryTransaction::create([
            'product_id' => $product->id,
            'quantity' => $stockQty,
            'transaction_type' => 'IN',
            'reference_type' => 'seed',
            'reference_id' => 1,
            'notes' => 'Initial stock',
        ]);

        return $product;
    }
}

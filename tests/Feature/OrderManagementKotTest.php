<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\InventoryTransaction;
use App\Models\Kot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementKotTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_screen_is_queue_only_without_manual_create_form(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);

        $response = $this->actingAs($cashier)->get(route('orders.index'));

        $response->assertOk();
        $response->assertSee('Order Queue');
        $response->assertDontSee('Create Order + KOT');
    }

    public function test_cashier_can_create_order_with_kot_and_view_print_template(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Order Test Cake', 8, 120, 'FG901');

        $response = $this->actingAs($cashier)->post(route('orders.store'), [
            'source' => 'outlet',
            'customer_identifier' => '',
            'customer_name' => 'Walk In',
            'customer_address' => '',
            'notes' => 'No cream on top',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('orders.index'));

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame('accepted', $order->status);
        $this->assertNotNull($order->accepted_at);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{5}$/', (string) $order->order_number);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'item_name' => 'Order Test Cake',
            'quantity' => 2.000,
            'unit_price' => 120.00,
            'line_total' => 240.00,
        ]);

        $kot = Kot::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($kot);
        $this->assertMatchesRegularExpression('/^KOT-\d{8}-\d{5}$/', (string) $kot->kot_number);

        $print = $this->actingAs($cashier)->get(route('orders.kot.print', ['order' => $order->id]));
        $print->assertOk();
        $print->assertSee('Kitchen Order Ticket');
        $print->assertSee('Order Test Cake');
    }

    public function test_completed_order_can_be_converted_to_invoice_via_pos_checkout(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Invoice From Order Cake', 10, 75, 'FG902');

        $order = Order::create([
            'order_number' => 'ORD-20260222-01001',
            'source' => 'outlet',
            'status' => 'completed',
            'customer_identifier' => '9000000001',
            'customer_name' => 'Outlet Customer',
            'customer_address' => 'Main Counter',
            'completed_at' => now(),
            'created_by_user_id' => $cashier->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'item_name' => $product->name,
            'unit' => 'pcs',
            'quantity' => 3,
            'unit_price' => 75,
            'line_total' => 225,
        ]);

        Kot::create([
            'order_id' => $order->id,
            'kot_number' => 'KOT-20260222-01001',
            'status' => 'open',
            'created_by_user_id' => $cashier->id,
        ]);

        $response = $this->actingAs($cashier)->post(route('pos.checkout'), [
            'order_id' => $order->id,
            'payment_mode' => 'cash',
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'paid_amount' => 225,
        ]);

        $response->assertRedirect(route('orders.index'));

        $order->refresh();
        $this->assertSame('invoiced', $order->status);
        $this->assertNotNull($order->sale_id);

        $sale = Sale::query()->find($order->sale_id);
        $this->assertNotNull($sale);
        $this->assertSame(225.0, (float) $sale->total_amount);
        $this->assertSame('ORD-20260222-01001', (string) $sale->order_reference);
        $this->assertSame('Outlet Customer', (string) $sale->customer_name_snapshot);

        $this->assertSame(7.0, $product->fresh()->currentStock());
    }

    public function test_purchase_role_cannot_access_orders_module(): void
    {
        $purchase = $this->createUser(User::ROLE_PURCHASE);

        $this->actingAs($purchase)->get(route('orders.index'))->assertForbidden();
        $this->actingAs($purchase)->post(route('orders.store'), [])->assertForbidden();
    }

    public function test_pos_send_to_kitchen_creates_order_and_kot_without_invoice(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('KOT Only Cake', 5, 90, 'FG903');

        $response = $this->actingAs($cashier)->post(route('pos.checkout'), [
            'submit_action' => 'kot',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'order_source' => 'outlet',
        ]);

        $response->assertRedirect(route('orders.index'));
        $response->assertSessionHas('success', 'Order sent to kitchen successfully.');

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('in_kitchen', $order->status);
        $this->assertNull($order->sale_id);
        $this->assertNotNull($order->in_kitchen_at);
        $this->assertNotNull($order->accepted_at);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2.000,
            'unit_price' => 90.00,
            'line_total' => 180.00,
        ]);
        $this->assertDatabaseHas('kots', [
            'order_id' => $order->id,
            'status' => 'open',
        ]);
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5.0, $product->fresh()->currentStock());
    }

    public function test_kot_mode_off_skips_kot_creation_for_new_orders(): void
    {
        BusinessSetting::create([
            'business_name' => 'BakeOps',
            'kot_mode' => 'off',
            'gst_enabled' => false,
        ]);

        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('No KOT Cake', 8, 95, 'FG904');

        $response = $this->actingAs($cashier)->post(route('orders.store'), [
            'source' => 'outlet',
            'customer_name' => 'Walk In',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect(route('orders.index'));
        $response->assertSessionHas('success', 'Order created.');

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertDatabaseMissing('kots', [
            'order_id' => $order->id,
        ]);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createFinishedProductWithStock(string $name, float $stockQty, float $price, string $code): Product
    {
        $product = Product::create([
            'code' => $code,
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

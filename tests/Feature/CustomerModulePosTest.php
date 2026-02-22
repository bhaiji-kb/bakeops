<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerModulePosTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_manage_customer_and_lookup_previous_purchase_history(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);

        $this->actingAs($cashier)->post(route('customers.store'), [
            'name' => 'Anita Patel',
            'mobile' => '9876543210',
            'identifier' => 'LOYAL-A1',
            'address_line1' => '123 Main Street',
            'road' => 'Baker Road',
            'sector' => 'Sector 9',
            'city' => 'Delhi',
            'pincode' => '110001',
            'preference' => 'Eggless cakes',
            'is_active' => 1,
        ])->assertRedirect(route('customers.index'));

        $customer = Customer::query()->first();
        $this->assertNotNull($customer);

        Sale::create([
            'bill_number' => 'INV-20260221-00091',
            'customer_id' => $customer->id,
            'customer_identifier' => $customer->mobile,
            'customer_name_snapshot' => $customer->name,
            'sub_total' => 200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'total_amount' => 200,
            'payment_mode' => 'cash',
            'order_source' => 'outlet',
            'paid_amount' => 200,
            'balance_amount' => 0,
        ]);

        $lookup = $this->actingAs($cashier)->getJson(route('pos.customers.lookup', [
            'identifier' => '9876543210',
        ]));

        $lookup->assertOk();
        $lookup->assertJsonPath('customer.name', 'Anita Patel');
        $lookup->assertJsonPath('customer.address', '123 Main Street, Baker Road, Sector 9, Delhi - 110001');
        $lookup->assertJsonPath('customer.preference', 'Eggless cakes');
        $lookup->assertJsonPath('stats.sales_count', 1);
        $lookup->assertJsonPath('stats.total_spent', 200);
    }

    public function test_checkout_auto_links_customer_by_identifier_and_saves_order_context(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $customer = Customer::create([
            'name' => 'Rohit Mehra',
            'mobile' => '9990001112',
            'identifier' => 'ROHIT-M',
            'address' => 'Skyline House, MG Road, Sector 2, Bengaluru - 560001',
            'address_line1' => 'Skyline House',
            'road' => 'MG Road',
            'sector' => 'Sector 2',
            'city' => 'Bengaluru',
            'pincode' => '560001',
            'is_active' => true,
        ]);
        $product = $this->createFinishedProductWithStock('Swiss Slice', 5, 90, 'FG101');

        $response = $this->actingAs($cashier)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'payment_mode' => 'upi',
            'customer_identifier' => '9990001112',
            'customer_name' => '',
            'customer_address' => '',
            'order_source' => 'swiggy',
            'order_reference' => 'SWG-ORDER-9001',
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'paid_amount' => 180,
        ]);

        $response->assertRedirect(route('pos.index'));

        $sale = Sale::query()->first();
        $this->assertNotNull($sale);
        $this->assertSame($customer->id, (int) $sale->customer_id);
        $this->assertSame('9990001112', $sale->customer_identifier);
        $this->assertSame('Rohit Mehra', $sale->customer_name_snapshot);
        $this->assertSame('Skyline House, MG Road, Sector 2, Bengaluru - 560001', $sale->customer_address_snapshot);
        $this->assertSame('swiggy', $sale->order_source);
        $this->assertSame('SWG-ORDER-9001', $sale->order_reference);
    }

    public function test_manual_checkout_auto_generates_order_reference_when_not_supplied(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Vanilla Slice', 5, 60, 'FG202');

        $response = $this->actingAs($cashier)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_mode' => 'cash',
            'order_source' => 'outlet',
            'customer_name' => 'Walk In',
            'customer_address' => 'Counter',
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'paid_amount' => 60,
        ]);

        $response->assertRedirect(route('pos.index'));

        $sale = Sale::query()->first();
        $this->assertNotNull($sale);
        $this->assertSame('Walk In', $sale->customer_name_snapshot);
        $this->assertSame('Counter', $sale->customer_address_snapshot);
        $this->assertNotNull($sale->order_reference);
        $this->assertMatchesRegularExpression('/^[A-Z]{3}-\d{14}-\d{4}$/', (string) $sale->order_reference);
    }

    public function test_checkout_auto_creates_customer_master_for_new_identifier(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Phone Order Slice', 6, 80, 'FG204');

        $response = $this->actingAs($cashier)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_mode' => 'upi',
            'order_source' => 'phone',
            'customer_identifier' => ' 98887 77666 ',
            'customer_name' => '  rohit   mehra  ',
            'customer_address_line1' => '  21  Baker Street  ',
            'customer_road' => '  Main Road ',
            'customer_sector' => ' Sector 4 ',
            'customer_city' => ' delhi ',
            'customer_pincode' => '110001',
            'customer_preference' => ' less sugar ',
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'paid_amount' => 80,
        ]);

        $response->assertRedirect(route('pos.index'));

        $sale = Sale::query()->first();
        $this->assertNotNull($sale);
        $this->assertNotNull($sale->customer_id);
        $this->assertSame('9888777666', (string) $sale->customer_identifier);
        $this->assertSame('Rohit Mehra', (string) $sale->customer_name_snapshot);

        $this->assertDatabaseHas('customers', [
            'id' => $sale->customer_id,
            'mobile' => '9888777666',
            'name' => 'Rohit Mehra',
            'address' => '21 Baker Street, Main Road, Sector 4, Delhi - 110001',
            'address_line1' => '21 Baker Street',
            'road' => 'Main Road',
            'sector' => 'Sector 4',
            'city' => 'Delhi',
            'pincode' => '110001',
            'preference' => 'less sugar',
            'is_active' => 1,
        ]);
    }

    public function test_purchase_role_cannot_manage_customers(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);
        $customer = Customer::create([
            'name' => 'Blocked Customer',
            'mobile' => '9123456789',
            'is_active' => true,
        ]);

        $this->actingAs($purchaseUser)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($purchaseUser)->post(route('customers.store'), [
            'name' => 'New Customer',
            'mobile' => '9000000001',
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('customers.edit', ['customer' => $customer->id]))->assertForbidden();
    }

    public function test_delivery_source_requires_customer_pii_when_customer_not_found(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = $this->createFinishedProductWithStock('Delivery Cake', 3, 150, 'FG303');

        $response = $this->actingAs($cashier)
            ->from(route('pos.index'))
            ->post(route('pos.checkout'), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'payment_mode' => 'upi',
                'order_source' => 'phone',
                'customer_identifier' => '9888777666',
                'customer_name' => '',
                'customer_address_line1' => '',
                'customer_city' => '',
                'customer_pincode' => '',
            ]);

        $response->assertRedirect(route('pos.index'));
        $response->assertSessionHasErrors([
            'customer_name' => 'Customer name is required for delivery orders.',
            'customer_address_line1' => 'Customer address is required for delivery orders.',
        ]);
    }

    public function test_customer_master_rejects_non_ten_digit_mobile(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);

        $response = $this->actingAs($cashier)
            ->from(route('customers.index'))
            ->post(route('customers.store'), [
                'name' => 'Invalid Mobile',
                'mobile' => '12345',
                'identifier' => '',
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHasErrors('mobile');
    }

    public function test_customers_page_supports_crm_segment_filters(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);

        $repeatCustomer = Customer::create([
            'name' => 'Repeat One',
            'mobile' => '9000000001',
            'is_active' => true,
        ]);
        $highValueCustomer = Customer::create([
            'name' => 'High Value One',
            'mobile' => '9000000002',
            'is_active' => true,
        ]);
        $newCustomer = Customer::create([
            'name' => 'New One',
            'mobile' => '9000000003',
            'is_active' => true,
        ]);

        Sale::create([
            'bill_number' => 'INV-20260222-00001',
            'customer_id' => $repeatCustomer->id,
            'customer_identifier' => $repeatCustomer->mobile,
            'customer_name_snapshot' => $repeatCustomer->name,
            'sub_total' => 200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'total_amount' => 200,
            'payment_mode' => 'cash',
            'order_source' => 'outlet',
            'paid_amount' => 200,
            'balance_amount' => 0,
        ]);
        Sale::create([
            'bill_number' => 'INV-20260222-00002',
            'customer_id' => $repeatCustomer->id,
            'customer_identifier' => $repeatCustomer->mobile,
            'customer_name_snapshot' => $repeatCustomer->name,
            'sub_total' => 250,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'total_amount' => 250,
            'payment_mode' => 'upi',
            'order_source' => 'outlet',
            'paid_amount' => 250,
            'balance_amount' => 0,
        ]);
        Sale::create([
            'bill_number' => 'INV-20260222-00003',
            'customer_id' => $highValueCustomer->id,
            'customer_identifier' => $highValueCustomer->mobile,
            'customer_name_snapshot' => $highValueCustomer->name,
            'sub_total' => 6000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'total_amount' => 6000,
            'payment_mode' => 'upi',
            'order_source' => 'outlet',
            'paid_amount' => 6000,
            'balance_amount' => 0,
        ]);

        $all = $this->actingAs($cashier)->get(route('customers.index'));
        $all->assertOk();
        $all->assertSee('Customer Insights!');
        $all->assertSee('Repeat Customers');
        $all->assertSee('High-Value Customers');
        $all->assertSee('New Customers');

        $repeat = $this->actingAs($cashier)->get(route('customers.index', ['segment' => 'repeat']));
        $repeat->assertOk();
        $repeat->assertSee('Repeat One');
        $repeat->assertDontSee('High Value One');
        $repeat->assertDontSee('New One');

        $highValue = $this->actingAs($cashier)->get(route('customers.index', ['segment' => 'high_value']));
        $highValue->assertOk();
        $highValue->assertSee('High Value One');
        $highValue->assertDontSee('Repeat One');
        $highValue->assertDontSee('New One');

        $new = $this->actingAs($cashier)->get(route('customers.index', ['segment' => 'new']));
        $new->assertOk();
        $new->assertSee('New One');
        $new->assertDontSee('Repeat One');
        $new->assertDontSee('High Value One');
    }

    public function test_customer_store_merges_notes_into_single_preference_field(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);

        $response = $this->actingAs($cashier)->post(route('customers.store'), [
            'name' => 'Merged Notes Customer',
            'mobile' => '9111111111',
            'notes' => 'Birthday reminders + no almonds',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'name' => 'Merged Notes Customer',
            'mobile' => '9111111111',
            'preference' => 'Birthday reminders + no almonds',
            'notes' => 'Birthday reminders + no almonds',
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

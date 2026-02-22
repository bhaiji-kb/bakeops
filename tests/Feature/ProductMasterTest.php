<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_product_and_activity_log_is_recorded(): void
    {
        $manager = $this->createUser(User::ROLE_MANAGER);

        $response = $this->actingAs($manager)->post(route('products.store'), [
            'name' => 'Flour',
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 5,
            'price' => 42.5,
            'opening_stock' => 2.5,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'code' => 'RM001',
            'name' => 'Flour',
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 5.00,
            'price' => 42.50,
            'is_active' => 1,
        ]);

        $product = Product::query()->where('name', 'Flour')->first();
        $this->assertNotNull($product);
        $this->assertSame('RM001', $product->code);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'transaction_type' => 'IN',
            'quantity' => 2.50,
            'notes' => 'Opening stock at product creation',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'products',
            'action' => 'create',
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_store_auto_generates_next_unique_code_by_type(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        Product::create([
            'code' => 'RM001',
            'name' => 'Existing Flour',
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 1,
            'price' => 10,
            'is_active' => true,
        ]);
        Product::create([
            'code' => 'RM002',
            'name' => 'Existing Sugar',
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 1,
            'price' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->post(route('products.store'), [
            'name' => 'Butter',
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 1,
            'price' => 30,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'name' => 'Butter',
            'code' => 'RM003',
            'type' => 'raw_material',
        ]);
    }

    public function test_purchase_user_can_update_product_and_log_contains_old_and_new_values(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);
        $product = Product::create([
            'code' => 'RM011',
            'name' => 'Milk',
            'type' => 'raw_material',
            'unit' => 'ltr',
            'reorder_level' => 10,
            'price' => 55,
            'is_active' => true,
        ]);

        $response = $this->actingAs($purchaseUser)->put(route('products.update', ['product' => $product->id]), [
            'code' => 'RM012',
            'name' => 'Milk Premium',
            'type' => 'raw_material',
            'unit' => 'ltr',
            'reorder_level' => 12,
            'price' => 60,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('products.index'));
        $product->refresh();
        $this->assertSame('RM012', $product->code);
        $this->assertSame('Milk Premium', $product->name);
        $this->assertEquals(12.0, (float) $product->reorder_level);
        $this->assertEquals(60.0, (float) $product->price);

        $log = ActivityLog::query()
            ->where('module', 'products')
            ->where('action', 'update')
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($purchaseUser->id, (int) $log->user_id);
        $this->assertSame('RM011', $log->old_values['code']);
        $this->assertSame('RM012', $log->new_values['code']);
        $this->assertSame('Milk', $log->old_values['name']);
        $this->assertSame('Milk Premium', $log->new_values['name']);
    }

    public function test_cashier_cannot_access_product_master_routes(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $product = Product::create([
            'code' => 'FG021',
            'name' => 'Vanilla Cake',
            'type' => 'finished_good',
            'unit' => 'pcs',
            'reorder_level' => 2,
            'price' => 350,
            'is_active' => true,
        ]);

        $this->actingAs($cashier)->get(route('products.index'))->assertForbidden();
        $this->actingAs($cashier)->post(route('products.store'), [
            'code' => 'RM022',
            'name' => 'Butter',
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 1,
            'price' => 10,
        ])->assertForbidden();
        $this->actingAs($cashier)->get(route('products.edit', ['product' => $product->id]))->assertForbidden();
        $this->actingAs($cashier)->put(route('products.update', ['product' => $product->id]), [
            'code' => 'FG023',
            'name' => 'Vanilla Cake Updated',
            'type' => 'finished_good',
            'unit' => 'pcs',
            'reorder_level' => 3,
            'price' => 360,
            'is_active' => 1,
        ])->assertForbidden();
    }

    public function test_product_master_validates_required_fields(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->from(route('products.index'))
            ->post(route('products.store'), [
                'name' => '',
                'type' => 'invalid',
                'unit' => 'invalid',
                'reorder_level' => -1,
                'price' => -1,
            ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHasErrors(['name', 'type', 'unit', 'reorder_level', 'price']);
        $this->assertDatabaseCount('products', 0);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }
}

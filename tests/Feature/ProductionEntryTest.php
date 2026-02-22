<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\RecipeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_production_batch_with_stock_movements_and_log(): void
    {
        $manager = $this->createUser(User::ROLE_MANAGER);
        $finished = $this->createFinishedProduct('Chocolate Cake');
        $flour = $this->createRawMaterial('Flour');
        $sugar = $this->createRawMaterial('Sugar');

        RecipeItem::create([
            'finished_product_id' => $finished->id,
            'ingredient_product_id' => $flour->id,
            'quantity' => 0.50,
        ]);
        RecipeItem::create([
            'finished_product_id' => $finished->id,
            'ingredient_product_id' => $sugar->id,
            'quantity' => 0.20,
        ]);

        $this->seedStock($flour, 10);
        $this->seedStock($sugar, 5);

        $response = $this->actingAs($manager)->post(route('production.store'), [
            'finished_product_id' => $finished->id,
            'quantity_produced' => 4,
            'produced_at' => '2026-02-21 10:30:00',
            'notes' => 'Morning batch',
        ]);

        $batch = ProductionBatch::query()->first();
        $this->assertNotNull($batch);

        $response->assertRedirect(route('production.index', [
            'product_id' => $finished->id,
            'quantity' => 4.0,
        ]));

        $this->assertEquals(4.0, (float) $batch->quantity_produced);
        $this->assertEquals(280.0, (float) $batch->total_ingredient_cost);
        $this->assertEquals(70.0, (float) $batch->unit_production_cost);
        $this->assertSame($manager->id, (int) $batch->produced_by);
        $this->assertSame('Morning batch', $batch->notes);

        $this->assertDatabaseHas('production_batch_items', [
            'production_batch_id' => $batch->id,
            'ingredient_product_id' => $flour->id,
            'quantity_per_unit' => 0.50,
            'quantity_used' => 2.00,
            'ingredient_unit_cost' => 100.0000,
            'total_cost' => 200.00,
        ]);
        $this->assertDatabaseHas('production_batch_items', [
            'production_batch_id' => $batch->id,
            'ingredient_product_id' => $sugar->id,
            'quantity_per_unit' => 0.20,
            'quantity_used' => 0.80,
            'ingredient_unit_cost' => 100.0000,
            'total_cost' => 80.00,
        ]);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $flour->id,
            'transaction_type' => 'OUT',
            'reference_type' => 'production_batch',
            'reference_id' => $batch->id,
            'quantity' => 2.00,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $sugar->id,
            'transaction_type' => 'OUT',
            'reference_type' => 'production_batch',
            'reference_id' => $batch->id,
            'quantity' => 0.80,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $finished->id,
            'transaction_type' => 'IN',
            'reference_type' => 'production_batch',
            'reference_id' => $batch->id,
            'quantity' => 4.00,
        ]);

        $this->assertEquals(8.0, (float) $flour->fresh()->currentStock());
        $this->assertEquals(4.2, (float) $sugar->fresh()->currentStock());
        $this->assertEquals(4.0, (float) $finished->fresh()->currentStock());
        $this->assertEquals(70.0, (float) $finished->fresh()->unit_cost);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'production',
            'action' => 'create_batch',
            'entity_type' => ProductionBatch::class,
            'entity_id' => $batch->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_production_fails_when_ingredient_stock_is_insufficient(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);
        $finished = $this->createFinishedProduct('Vanilla Cake');
        $flour = $this->createRawMaterial('Flour');

        RecipeItem::create([
            'finished_product_id' => $finished->id,
            'ingredient_product_id' => $flour->id,
            'quantity' => 1.00,
        ]);

        $this->seedStock($flour, 1);

        $response = $this->actingAs($purchaseUser)
            ->from(route('production.index'))
            ->post(route('production.store'), [
                'finished_product_id' => $finished->id,
                'quantity_produced' => 2,
            ]);

        $response->assertRedirect(route('production.index'));
        $response->assertSessionHasErrors(['production']);

        $this->assertDatabaseCount('production_batches', 0);
        $this->assertDatabaseCount('production_batch_items', 0);
        $this->assertSame(1, InventoryTransaction::query()->count());
        $this->assertDatabaseMissing('activity_logs', [
            'module' => 'production',
            'action' => 'create_batch',
        ]);
    }

    public function test_production_fails_when_recipe_is_missing(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $finished = $this->createFinishedProduct('Red Velvet');

        $response = $this->actingAs($owner)
            ->from(route('production.index'))
            ->post(route('production.store'), [
                'finished_product_id' => $finished->id,
                'quantity_produced' => 1,
            ]);

        $response->assertRedirect(route('production.index'));
        $response->assertSessionHasErrors([
            'production' => 'Recipe is not configured for this finished product.',
        ]);

        $this->assertDatabaseCount('production_batches', 0);
        $this->assertDatabaseCount('production_batch_items', 0);
    }

    public function test_cashier_cannot_access_production_routes(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $finished = $this->createFinishedProduct('Pineapple Cake');

        $this->actingAs($cashier)->get(route('production.index'))->assertForbidden();
        $this->actingAs($cashier)->post(route('production.store'), [
            'finished_product_id' => $finished->id,
            'quantity_produced' => 1,
        ])->assertForbidden();
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createRawMaterial(string $name): Product
    {
        return Product::create([
            'name' => $name,
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 1,
            'price' => 100,
            'is_active' => true,
        ]);
    }

    private function createFinishedProduct(string $name): Product
    {
        return Product::create([
            'name' => $name,
            'type' => 'finished_good',
            'unit' => 'pcs',
            'reorder_level' => 1,
            'price' => 350,
            'is_active' => true,
        ]);
    }

    private function seedStock(Product $product, float $quantity): void
    {
        InventoryTransaction::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'transaction_type' => 'IN',
            'reference_type' => 'seed',
            'reference_id' => 1,
            'notes' => 'Initial stock',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeBomTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_recipe_items_for_finished_product(): void
    {
        $manager = $this->createUser(User::ROLE_MANAGER);
        $finished = $this->createFinishedProduct('Black Forest');
        $flour = $this->createRawMaterial('Flour');
        $sugar = $this->createRawMaterial('Sugar');

        $response = $this->actingAs($manager)->put(route('recipes.update', ['product' => $finished->id]), [
            'rows' => [
                ['ingredient_product_id' => $flour->id, 'quantity' => 0.50],
                ['ingredient_product_id' => $sugar->id, 'quantity' => 0.20],
            ],
        ]);

        $response->assertRedirect(route('recipes.edit', ['product' => $finished->id]));
        $this->assertDatabaseHas('recipe_items', [
            'finished_product_id' => $finished->id,
            'ingredient_product_id' => $flour->id,
            'quantity' => 0.50,
        ]);
        $this->assertDatabaseHas('recipe_items', [
            'finished_product_id' => $finished->id,
            'ingredient_product_id' => $sugar->id,
            'quantity' => 0.20,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'recipes',
            'action' => 'upsert',
            'entity_type' => Product::class,
            'entity_id' => $finished->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_purchase_user_can_replace_existing_recipe_items(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);
        $finished = $this->createFinishedProduct('Puff');
        $oldIngredient = $this->createRawMaterial('Old Ingredient');
        $newIngredient = $this->createRawMaterial('New Ingredient');

        RecipeItem::create([
            'finished_product_id' => $finished->id,
            'ingredient_product_id' => $oldIngredient->id,
            'quantity' => 1.00,
        ]);

        $response = $this->actingAs($purchaseUser)->put(route('recipes.update', ['product' => $finished->id]), [
            'rows' => [
                ['ingredient_product_id' => $newIngredient->id, 'quantity' => 2.25],
            ],
        ]);

        $response->assertRedirect(route('recipes.edit', ['product' => $finished->id]));
        $this->assertDatabaseMissing('recipe_items', [
            'finished_product_id' => $finished->id,
            'ingredient_product_id' => $oldIngredient->id,
        ]);
        $this->assertDatabaseHas('recipe_items', [
            'finished_product_id' => $finished->id,
            'ingredient_product_id' => $newIngredient->id,
            'quantity' => 2.25,
        ]);
    }

    public function test_recipe_update_requires_at_least_one_positive_quantity(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $finished = $this->createFinishedProduct('Croissant');
        $ingredient = $this->createRawMaterial('Butter');

        $response = $this->actingAs($owner)
            ->from(route('recipes.edit', ['product' => $finished->id]))
            ->put(route('recipes.update', ['product' => $finished->id]), [
                'rows' => [
                    ['ingredient_product_id' => $ingredient->id, 'quantity' => 0],
                ],
            ]);

        $response->assertRedirect(route('recipes.edit', ['product' => $finished->id]));
        $response->assertSessionHasErrors([
            'rows' => 'Add at least one ingredient quantity greater than 0.',
        ]);
        $this->assertDatabaseCount('recipe_items', 0);
    }

    public function test_recipe_update_rejects_non_raw_material_ingredients(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $finished = $this->createFinishedProduct('Muffin');
        $invalidIngredient = $this->createFinishedProduct('Invalid Ingredient');

        $response = $this->actingAs($owner)
            ->from(route('recipes.edit', ['product' => $finished->id]))
            ->put(route('recipes.update', ['product' => $finished->id]), [
                'rows' => [
                    ['ingredient_product_id' => $invalidIngredient->id, 'quantity' => 1.50],
                ],
            ]);

        $response->assertRedirect(route('recipes.edit', ['product' => $finished->id]));
        $response->assertSessionHasErrors([
            'rows' => 'Only raw material products can be used as recipe ingredients.',
        ]);
        $this->assertDatabaseCount('recipe_items', 0);
    }

    public function test_cashier_cannot_access_recipe_routes(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $finished = $this->createFinishedProduct('Truffle Cake');
        $ingredient = $this->createRawMaterial('Cocoa Powder');

        $this->actingAs($cashier)->get(route('recipes.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('recipes.edit', ['product' => $finished->id]))->assertForbidden();
        $this->actingAs($cashier)->put(route('recipes.update', ['product' => $finished->id]), [
            'rows' => [
                ['ingredient_product_id' => $ingredient->id, 'quantity' => 0.80],
            ],
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
            'price' => 300,
            'is_active' => true,
        ]);
    }
}


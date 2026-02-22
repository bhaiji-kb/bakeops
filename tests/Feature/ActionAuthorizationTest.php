<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_mutating_routes(): void
    {
        $expense = Expense::create([
            'expense_date' => '2026-02-20',
            'category' => 'rent',
            'amount' => 500,
            'notes' => 'Rent',
        ]);
        $product = Product::create([
            'name' => 'Guest Product',
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 1,
            'price' => 10,
            'is_active' => true,
        ]);
        $recipeProduct = Product::create([
            'name' => 'Guest Recipe Product',
            'type' => 'finished_good',
            'unit' => 'pcs',
            'reorder_level' => 1,
            'price' => 100,
            'is_active' => true,
        ]);
        $recipeIngredient = Product::create([
            'name' => 'Guest Recipe Ingredient',
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 1,
            'price' => 20,
            'is_active' => true,
        ]);

        $this->post(route('pos.checkout'), [])->assertRedirect(route('login'));
        $this->post(route('products.store'), [])->assertRedirect(route('login'));
        $this->put(route('products.update', ['product' => $product->id]), [])->assertRedirect(route('login'));
        $this->put(route('recipes.update', ['product' => $recipeProduct->id]), [
            'rows' => [
                ['ingredient_product_id' => $recipeIngredient->id, 'quantity' => 1],
            ],
        ])->assertRedirect(route('login'));
        $this->post(route('production.store'), [])->assertRedirect(route('login'));
        $this->post(route('purchases.store'), [])->assertRedirect(route('login'));
        $this->post(route('expenses.store'), [])->assertRedirect(route('login'));
        $this->post(route('customers.store'), [])->assertRedirect(route('login'));
        $this->put(route('expenses.update', ['expense' => $expense->id]), [])->assertRedirect(route('login'));
        $this->delete(route('expenses.destroy', ['expense' => $expense->id]), [])->assertRedirect(route('login'));
        $this->post(route('users.store'), [])->assertRedirect(route('login'));
    }

    public function test_cashier_can_checkout_but_cannot_run_purchase_expense_or_user_actions(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $supplier = $this->createSupplier();
        $rawMaterial = $this->createRawMaterial('Flour');
        $recipeFinished = $this->createFinishedProductWithStock('Recipe Cake', 2, 200);
        $editableProduct = $this->createRawMaterial('Cocoa');
        $expense = $this->createExpense();
        $targetUser = $this->createUser(User::ROLE_MANAGER);
        $finishedProduct = $this->createFinishedProductWithStock('Cupcake', 5, 50);

        $checkout = $this->actingAs($cashier)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $finishedProduct->id, 'quantity' => 1],
            ],
            'payment_mode' => 'cash',
        ]);
        $checkout->assertRedirect();
        $this->assertDatabaseHas('sales', [
            'payment_mode' => 'cash',
            'total_amount' => 50.00,
        ]);
        $this->actingAs($cashier)->post(route('customers.store'), $this->customerStorePayload('Cashier Customer', '9876543210'))->assertRedirect(route('customers.index'));

        $this->actingAs($cashier)->post(route('products.store'), $this->productStorePayload('Cashier Product'))->assertForbidden();
        $this->actingAs($cashier)->put(route('products.update', ['product' => $editableProduct->id]), $this->productUpdatePayload())->assertForbidden();
        $this->actingAs($cashier)->put(route('recipes.update', ['product' => $recipeFinished->id]), [
            'rows' => [
                ['ingredient_product_id' => $rawMaterial->id, 'quantity' => 1],
            ],
        ])->assertForbidden();
        $this->actingAs($cashier)->post(route('production.store'), [
            'finished_product_id' => $recipeFinished->id,
            'quantity_produced' => 1,
        ])->assertForbidden();
        $this->actingAs($cashier)->post(route('purchases.store'), $this->purchasePayload($supplier->id, $rawMaterial->id))->assertForbidden();
        $this->actingAs($cashier)->post(route('expenses.store'), $this->expensePayload())->assertForbidden();
        $this->actingAs($cashier)->put(route('expenses.update', ['expense' => $expense->id]), $this->expensePayload())->assertForbidden();
        $this->actingAs($cashier)->delete(route('expenses.destroy', ['expense' => $expense->id]), [])->assertForbidden();
        $this->actingAs($cashier)->post(route('users.store'), $this->userStorePayload())->assertForbidden();
        $this->actingAs($cashier)->put(route('users.update', ['user' => $targetUser->id]), $this->userUpdatePayload())->assertForbidden();
    }

    public function test_purchase_role_can_manage_purchases_but_cannot_run_pos_expenses_or_users_actions(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);
        $supplier = $this->createSupplier();
        $rawMaterial = $this->createRawMaterial('Sugar');
        $recipeFinished = $this->createFinishedProductWithStock('Purchase Recipe Bread', 2, 120);
        $editableProduct = $this->createRawMaterial('Salt');
        $expense = $this->createExpense();
        $targetUser = $this->createUser(User::ROLE_MANAGER);
        $finishedProduct = $this->createFinishedProductWithStock('Bread', 5, 40);

        $this->actingAs($purchaseUser)->post(route('products.store'), $this->productStorePayload('Purchase Product'))->assertRedirect(route('products.index'));
        $this->actingAs($purchaseUser)->put(route('products.update', ['product' => $editableProduct->id]), $this->productUpdatePayload())->assertRedirect(route('products.index'));
        $this->actingAs($purchaseUser)->put(route('recipes.update', ['product' => $recipeFinished->id]), [
            'rows' => [
                ['ingredient_product_id' => $rawMaterial->id, 'quantity' => 1.5],
            ],
        ])->assertRedirect(route('recipes.edit', ['product' => $recipeFinished->id]));
        $this->actingAs($purchaseUser)
            ->from(route('production.index'))
            ->post(route('production.store'), [
                'finished_product_id' => $recipeFinished->id,
                'quantity_produced' => 1,
            ])
            ->assertRedirect(route('production.index'));

        $storeResponse = $this->actingAs($purchaseUser)->post(route('purchases.store'), $this->purchasePayload($supplier->id, $rawMaterial->id));
        $purchase = Purchase::query()->first();
        $this->assertNotNull($purchase);
        $storeResponse->assertRedirect(route('purchases.show', ['purchase' => $purchase->id]));

        $paymentResponse = $this->actingAs($purchaseUser)->post(route('purchases.payments.store', ['purchase' => $purchase->id]), [
            'payment_date' => '2026-02-21',
            'amount' => 40,
            'payment_mode' => 'cash',
            'notes' => 'Payment',
        ]);
        $paymentResponse->assertRedirect(route('purchases.show', ['purchase' => $purchase->id]));

        $this->actingAs($purchaseUser)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $finishedProduct->id, 'quantity' => 1],
            ],
            'payment_mode' => 'cash',
        ])->assertForbidden();

        $this->actingAs($purchaseUser)->post(route('expenses.store'), $this->expensePayload())->assertForbidden();
        $this->actingAs($purchaseUser)->put(route('expenses.update', ['expense' => $expense->id]), $this->expensePayload())->assertForbidden();
        $this->actingAs($purchaseUser)->delete(route('expenses.destroy', ['expense' => $expense->id]), [])->assertForbidden();
        $this->actingAs($purchaseUser)->post(route('users.store'), $this->userStorePayload())->assertForbidden();
        $this->actingAs($purchaseUser)->post(route('customers.store'), $this->customerStorePayload('Purchase Customer', '9876543211'))->assertForbidden();
        $this->actingAs($purchaseUser)->post(route('users.reset_password', ['user' => $targetUser->id]), [
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertForbidden();
    }

    public function test_manager_can_run_pos_purchase_expense_actions_but_not_owner_user_actions(): void
    {
        $manager = $this->createUser(User::ROLE_MANAGER);
        $supplier = $this->createSupplier();
        $rawMaterial = $this->createRawMaterial('Butter');
        $recipeFinished = $this->createFinishedProductWithStock('Manager Recipe Donut', 2, 70);
        $editableProduct = $this->createRawMaterial('Yeast');
        $finishedProduct = $this->createFinishedProductWithStock('Donut', 5, 30);
        $expense = $this->createExpense();
        $targetUser = $this->createUser(User::ROLE_CASHIER);

        $this->actingAs($manager)->post(route('products.store'), $this->productStorePayload('Manager Product'))->assertRedirect(route('products.index'));
        $this->actingAs($manager)->put(route('products.update', ['product' => $editableProduct->id]), $this->productUpdatePayload())->assertRedirect(route('products.index'));
        $this->actingAs($manager)->put(route('recipes.update', ['product' => $recipeFinished->id]), [
            'rows' => [
                ['ingredient_product_id' => $rawMaterial->id, 'quantity' => 1.25],
            ],
        ])->assertRedirect(route('recipes.edit', ['product' => $recipeFinished->id]));
        $this->actingAs($manager)
            ->from(route('production.index'))
            ->post(route('production.store'), [
                'finished_product_id' => $recipeFinished->id,
                'quantity_produced' => 1,
            ])
            ->assertRedirect(route('production.index'));

        $this->actingAs($manager)->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $finishedProduct->id, 'quantity' => 1],
            ],
            'payment_mode' => 'upi',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('customers.store'), $this->customerStorePayload('Manager Customer', '9876543212'))->assertRedirect(route('customers.index'));

        $purchaseStore = $this->actingAs($manager)->post(route('purchases.store'), $this->purchasePayload($supplier->id, $rawMaterial->id));
        $purchase = Purchase::query()->latest('id')->first();
        $this->assertNotNull($purchase);
        $purchaseStore->assertRedirect(route('purchases.show', ['purchase' => $purchase->id]));

        $this->actingAs($manager)->post(route('purchases.payments.store', ['purchase' => $purchase->id]), [
            'payment_date' => '2026-02-21',
            'amount' => 40,
            'payment_mode' => 'bank',
            'notes' => 'Settlement',
        ])->assertRedirect(route('purchases.show', ['purchase' => $purchase->id]));

        $this->actingAs($manager)->post(route('expenses.store'), $this->expensePayload())->assertRedirect();
        $this->actingAs($manager)->put(route('expenses.update', ['expense' => $expense->id]), array_merge($this->expensePayload(), ['month' => '2026-02']))->assertRedirect();
        $this->actingAs($manager)->delete(route('expenses.destroy', ['expense' => $expense->id]), ['month' => '2026-02'])->assertRedirect();

        $this->actingAs($manager)->post(route('users.store'), $this->userStorePayload())->assertForbidden();
        $this->actingAs($manager)->put(route('users.update', ['user' => $targetUser->id]), $this->userUpdatePayload())->assertForbidden();
        $this->actingAs($manager)->post(route('users.reset_password', ['user' => $targetUser->id]), [
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertForbidden();
    }

    public function test_owner_can_execute_owner_only_user_management_actions(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $targetUser = $this->createUser(User::ROLE_CASHIER);

        $storeResponse = $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'New Manager',
            'email' => 'new-manager@example.com',
            'role' => User::ROLE_MANAGER,
            'is_active' => 1,
            'password' => 'password',
        ]);
        $storeResponse->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'new-manager@example.com',
            'role' => User::ROLE_MANAGER,
            'is_active' => 1,
        ]);

        $updateResponse = $this->actingAs($owner)->put(route('users.update', ['user' => $targetUser->id]), [
            'name' => 'Updated Cashier',
            'email' => $targetUser->email,
            'role' => User::ROLE_CASHIER,
            'is_active' => 1,
        ]);
        $updateResponse->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Updated Cashier',
        ]);

        $resetResponse = $this->actingAs($owner)->post(route('users.reset_password', ['user' => $targetUser->id]), [
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ]);
        $resetResponse->assertRedirect();
    }

    private function createUser(string $role, bool $isActive = true): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => $isActive,
            'password' => 'password',
        ]);
    }

    private function createSupplier(): Supplier
    {
        return Supplier::create([
            'name' => 'Supplier One',
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
            'price' => 0,
            'is_active' => true,
        ]);
    }

    private function createFinishedProductWithStock(string $name, float $stockQty, float $price): Product
    {
        $product = Product::create([
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

    private function createExpense(): Expense
    {
        return Expense::create([
            'expense_date' => '2026-02-20',
            'category' => 'rent',
            'amount' => 500,
            'notes' => 'Rent',
        ]);
    }

    private function purchasePayload(int $supplierId, int $productId): array
    {
        return [
            'supplier_id' => $supplierId,
            'bill_number' => 'BILL-100',
            'purchase_date' => '2026-02-21',
            'notes' => 'Purchase',
            'initial_paid_amount' => 10,
            'initial_payment_mode' => 'cash',
            'items' => [
                [
                    'product_id' => $productId,
                    'quantity' => 2,
                    'unit_price' => 25,
                ],
            ],
        ];
    }

    private function expensePayload(): array
    {
        return [
            'expense_date' => '2026-02-21',
            'category' => 'salary',
            'amount' => 1200,
            'notes' => 'Team salary',
        ];
    }

    private function userStorePayload(): array
    {
        return [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'role' => User::ROLE_CASHIER,
            'is_active' => 1,
            'password' => 'password',
        ];
    }

    private function userUpdatePayload(): array
    {
        return [
            'name' => 'Any User',
            'email' => 'any@example.com',
            'role' => User::ROLE_CASHIER,
            'is_active' => 1,
        ];
    }

    private function productStorePayload(string $name): array
    {
        return [
            'code' => 'RM031',
            'name' => $name,
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 2,
            'price' => 100,
            'is_active' => 1,
        ];
    }

    private function productUpdatePayload(): array
    {
        return [
            'code' => 'RM032',
            'name' => 'Updated Product Name',
            'type' => 'raw_material',
            'unit' => 'kg',
            'reorder_level' => 3,
            'price' => 120,
            'is_active' => 1,
        ];
    }

    private function customerStorePayload(string $name, string $mobile): array
    {
        return [
            'name' => $name,
            'mobile' => $mobile,
            'identifier' => '',
            'is_active' => 1,
        ];
    }
}

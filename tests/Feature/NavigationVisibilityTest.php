<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_full_navigation(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);

        $response = $this->actingAs($owner)->get(route('orders.index'));

        $response->assertOk();
        $response->assertDontSee('href="' . route('pos.index') . '"', false);
        $response->assertSee('href="' . route('pos.online_orders.index') . '"', false);
        $response->assertSee('href="' . route('orders.index') . '"', false);
        $response->assertSee('href="' . route('pos.sales.index') . '"', false);
        $response->assertSee('href="' . route('customers.index') . '"', false);
        $response->assertSee('href="' . route('products.index') . '"', false);
        $response->assertSee('href="' . route('recipes.index') . '"', false);
        $response->assertSee('href="' . route('production.index') . '"', false);
        $response->assertSee('href="' . route('inventory.index') . '"', false);
        $response->assertSee('href="' . route('suppliers.index') . '"', false);
        $response->assertSee('href="' . route('purchases.index') . '"', false);
        $response->assertSee('href="' . route('reports.suppliers.ledger') . '"', false);
        $response->assertSee('href="' . route('accounts.dashboard') . '"', false);
        $response->assertSee('href="' . route('expenses.index') . '"', false);
        $response->assertSee('href="' . route('reports.sales.daily') . '"', false);
        $response->assertSee('href="' . route('reports.profit_loss.monthly') . '"', false);
        $response->assertSee('href="' . route('logs.index') . '"', false);
        $response->assertSee('href="' . route('users.index') . '"', false);
        $response->assertSee('href="' . route('admin.settings.index') . '"', false);
        $response->assertSee('href="' . route('integrations.connectors.index') . '"', false);
    }

    public function test_manager_does_not_see_users_navigation(): void
    {
        $manager = $this->createUser(User::ROLE_MANAGER);

        $response = $this->actingAs($manager)->get(route('orders.index'));

        $response->assertOk();
        $response->assertDontSee('href="' . route('pos.index') . '"', false);
        $response->assertSee('href="' . route('pos.online_orders.index') . '"', false);
        $response->assertSee('href="' . route('orders.index') . '"', false);
        $response->assertSee('href="' . route('pos.sales.index') . '"', false);
        $response->assertSee('href="' . route('customers.index') . '"', false);
        $response->assertSee('href="' . route('products.index') . '"', false);
        $response->assertSee('href="' . route('recipes.index') . '"', false);
        $response->assertSee('href="' . route('production.index') . '"', false);
        $response->assertSee('href="' . route('inventory.index') . '"', false);
        $response->assertSee('href="' . route('purchases.index') . '"', false);
        $response->assertSee('href="' . route('accounts.dashboard') . '"', false);
        $response->assertSee('href="' . route('expenses.index') . '"', false);
        $response->assertSee('href="' . route('logs.index') . '"', false);
        $response->assertDontSee('href="' . route('users.index') . '"', false);
        $response->assertDontSee('href="' . route('admin.settings.index') . '"', false);
        $response->assertDontSee('href="' . route('integrations.connectors.index') . '"', false);
    }

    public function test_purchase_role_sees_procurement_navigation_only(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);

        $response = $this->actingAs($purchaseUser)->get(route('purchases.index'));

        $response->assertOk();
        $response->assertSee('href="' . route('products.index') . '"', false);
        $response->assertSee('href="' . route('recipes.index') . '"', false);
        $response->assertSee('href="' . route('production.index') . '"', false);
        $response->assertSee('href="' . route('inventory.index') . '"', false);
        $response->assertSee('href="' . route('suppliers.index') . '"', false);
        $response->assertSee('href="' . route('purchases.index') . '"', false);
        $response->assertSee('href="' . route('reports.suppliers.ledger') . '"', false);
        $response->assertDontSee('href="' . route('accounts.dashboard') . '"', false);
        $response->assertDontSee('href="' . route('customers.index') . '"', false);
        $response->assertDontSee('href="' . route('pos.index') . '"', false);
        $response->assertDontSee('href="' . route('pos.online_orders.index') . '"', false);
        $response->assertDontSee('href="' . route('orders.index') . '"', false);
        $response->assertDontSee('href="' . route('pos.sales.index') . '"', false);
        $response->assertDontSee('href="' . route('expenses.index') . '"', false);
        $response->assertDontSee('href="' . route('reports.sales.daily') . '"', false);
        $response->assertDontSee('href="' . route('reports.profit_loss.monthly') . '"', false);
        $response->assertDontSee('href="' . route('logs.index') . '"', false);
        $response->assertDontSee('href="' . route('users.index') . '"', false);
        $response->assertDontSee('href="' . route('admin.settings.index') . '"', false);
        $response->assertDontSee('href="' . route('integrations.connectors.index') . '"', false);
    }

    public function test_cashier_sees_operations_navigation_only(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);

        $response = $this->actingAs($cashier)->get(route('orders.index'));

        $response->assertOk();
        $response->assertDontSee('href="' . route('pos.index') . '"', false);
        $response->assertSee('href="' . route('pos.online_orders.index') . '"', false);
        $response->assertSee('href="' . route('orders.index') . '"', false);
        $response->assertSee('href="' . route('pos.sales.index') . '"', false);
        $response->assertSee('href="' . route('customers.index') . '"', false);
        $response->assertDontSee('href="' . route('products.index') . '"', false);
        $response->assertDontSee('href="' . route('recipes.index') . '"', false);
        $response->assertDontSee('href="' . route('production.index') . '"', false);
        $response->assertDontSee('href="' . route('inventory.index') . '"', false);
        $response->assertDontSee('href="' . route('suppliers.index') . '"', false);
        $response->assertDontSee('href="' . route('purchases.index') . '"', false);
        $response->assertDontSee('href="' . route('reports.suppliers.ledger') . '"', false);
        $response->assertDontSee('href="' . route('accounts.dashboard') . '"', false);
        $response->assertDontSee('href="' . route('expenses.index') . '"', false);
        $response->assertDontSee('href="' . route('reports.sales.daily') . '"', false);
        $response->assertDontSee('href="' . route('reports.profit_loss.monthly') . '"', false);
        $response->assertDontSee('href="' . route('logs.index') . '"', false);
        $response->assertDontSee('href="' . route('users.index') . '"', false);
        $response->assertDontSee('href="' . route('admin.settings.index') . '"', false);
        $response->assertDontSee('href="' . route('integrations.connectors.index') . '"', false);
    }

    private function createUser(string $role, bool $isActive = true): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => $isActive,
        ]);
    }
}

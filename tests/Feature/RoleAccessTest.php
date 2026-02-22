<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_protected_routes(): void
    {
        $this->get(route('pos.index'))->assertRedirect(route('login'));
        $this->get(route('purchases.index'))->assertRedirect(route('login'));
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_owner_can_access_all_core_module_pages(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER);
        $sale = Sale::create([
            'bill_number' => 'INV-20260222-00001',
            'total_amount' => 100,
            'payment_mode' => 'cash',
        ]);

        $this->actingAs($owner)->get(route('pos.index'))->assertOk();
        $this->actingAs($owner)->get(route('pos.online_orders.index'))->assertOk();
        $this->actingAs($owner)->getJson(route('pos.products.search', ['q' => '9']))->assertOk();
        $this->actingAs($owner)->getJson(route('pos.products.lookup', ['code' => '99']))->assertStatus(404);
        $this->actingAs($owner)->get(route('customers.index'))->assertOk();
        $this->actingAs($owner)->get(route('pos.sales.index'))->assertOk();
        $this->actingAs($owner)->get(route('pos.sales.invoice.pdf', ['sale' => $sale->id]))->assertOk();
        $this->actingAs($owner)->get(route('products.index'))->assertOk();
        $this->actingAs($owner)->get(route('recipes.index'))->assertOk();
        $this->actingAs($owner)->get(route('production.index'))->assertOk();
        $this->actingAs($owner)->get(route('inventory.index'))->assertOk();
        $this->actingAs($owner)->get(route('purchases.index'))->assertOk();
        $this->actingAs($owner)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($owner)->get(route('accounts.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('accounts.dashboard.export.pdf'))->assertOk();
        $this->actingAs($owner)->get(route('accounts.dashboard.export.excel'))->assertOk();
        $this->actingAs($owner)->get(route('expenses.index'))->assertOk();
        $this->actingAs($owner)->get(route('logs.index'))->assertOk();
        $this->actingAs($owner)->get(route('users.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.settings.index'))->assertOk();
        $this->actingAs($owner)->get(route('integrations.connectors.index'))->assertOk();
    }

    public function test_manager_is_blocked_from_owner_only_user_management(): void
    {
        $manager = $this->createUser(User::ROLE_MANAGER);
        $sale = Sale::create([
            'bill_number' => 'INV-20260222-00002',
            'total_amount' => 100,
            'payment_mode' => 'cash',
        ]);

        $this->actingAs($manager)->get(route('pos.index'))->assertOk();
        $this->actingAs($manager)->get(route('pos.online_orders.index'))->assertOk();
        $this->actingAs($manager)->getJson(route('pos.products.search', ['q' => '9']))->assertOk();
        $this->actingAs($manager)->getJson(route('pos.products.lookup', ['code' => '99']))->assertStatus(404);
        $this->actingAs($manager)->get(route('customers.index'))->assertOk();
        $this->actingAs($manager)->get(route('pos.sales.index'))->assertOk();
        $this->actingAs($manager)->get(route('pos.sales.invoice.pdf', ['sale' => $sale->id]))->assertOk();
        $this->actingAs($manager)->get(route('products.index'))->assertOk();
        $this->actingAs($manager)->get(route('recipes.index'))->assertOk();
        $this->actingAs($manager)->get(route('production.index'))->assertOk();
        $this->actingAs($manager)->get(route('purchases.index'))->assertOk();
        $this->actingAs($manager)->get(route('accounts.dashboard'))->assertOk();
        $this->actingAs($manager)->get(route('accounts.dashboard.export.pdf'))->assertOk();
        $this->actingAs($manager)->get(route('accounts.dashboard.export.excel'))->assertOk();
        $this->actingAs($manager)->get(route('expenses.index'))->assertOk();
        $this->actingAs($manager)->get(route('users.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('integrations.connectors.index'))->assertForbidden();
    }

    public function test_cashier_only_has_pos_access(): void
    {
        $cashier = $this->createUser(User::ROLE_CASHIER);
        $sale = Sale::create([
            'bill_number' => 'INV-20260222-00003',
            'total_amount' => 100,
            'payment_mode' => 'cash',
        ]);

        $this->actingAs($cashier)->get(route('pos.index'))->assertOk();
        $this->actingAs($cashier)->get(route('pos.online_orders.index'))->assertOk();
        $this->actingAs($cashier)->getJson(route('pos.products.search', ['q' => '9']))->assertOk();
        $this->actingAs($cashier)->getJson(route('pos.products.lookup', ['code' => '99']))->assertStatus(404);
        $this->actingAs($cashier)->get(route('customers.index'))->assertOk();
        $this->actingAs($cashier)->get(route('pos.sales.index'))->assertOk();
        $this->actingAs($cashier)->get(route('pos.sales.invoice.pdf', ['sale' => $sale->id]))->assertOk();
        $this->actingAs($cashier)->get(route('products.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('recipes.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('production.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('purchases.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('inventory.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('accounts.dashboard'))->assertForbidden();
        $this->actingAs($cashier)->get(route('accounts.dashboard.export.pdf'))->assertForbidden();
        $this->actingAs($cashier)->get(route('accounts.dashboard.export.excel'))->assertForbidden();
        $this->actingAs($cashier)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('users.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('integrations.connectors.index'))->assertForbidden();
    }

    public function test_purchase_role_has_procurement_access_but_not_pos_or_expenses(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE);
        $sale = Sale::create([
            'bill_number' => 'INV-20260222-00004',
            'total_amount' => 100,
            'payment_mode' => 'cash',
        ]);

        $this->actingAs($purchaseUser)->get(route('purchases.index'))->assertOk();
        $this->actingAs($purchaseUser)->get(route('pos.online_orders.index'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('pos.products.search', ['q' => '9']))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('pos.products.lookup', ['code' => '99']))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('pos.sales.index'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('pos.sales.invoice.pdf', ['sale' => $sale->id]))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('products.index'))->assertOk();
        $this->actingAs($purchaseUser)->get(route('recipes.index'))->assertOk();
        $this->actingAs($purchaseUser)->get(route('production.index'))->assertOk();
        $this->actingAs($purchaseUser)->get(route('inventory.index'))->assertOk();
        $this->actingAs($purchaseUser)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($purchaseUser)->get(route('reports.suppliers.ledger'))->assertOk();
        $this->actingAs($purchaseUser)->get(route('accounts.dashboard'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('accounts.dashboard.export.pdf'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('accounts.dashboard.export.excel'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('pos.index'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('users.index'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($purchaseUser)->get(route('integrations.connectors.index'))->assertForbidden();
    }

    public function test_inactive_user_is_logged_out_and_redirected_to_login(): void
    {
        $inactiveOwner = $this->createUser(User::ROLE_OWNER, false);

        $response = $this->actingAs($inactiveOwner)->get(route('pos.index'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Your account is inactive. Contact administrator.',
        ]);
        $this->assertGuest();
    }

    private function createUser(string $role, bool $isActive = true): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => $isActive,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = $this->createUser(User::ROLE_OWNER, true);

        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Invalid email or password.',
        ]);
        $this->assertGuest();
        $this->assertDatabaseMissing('activity_logs', [
            'module' => 'auth',
            'action' => 'login',
            'user_id' => $user->id,
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $inactiveManager = $this->createUser(User::ROLE_MANAGER, false);

        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => $inactiveManager->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Your account is inactive. Contact administrator.',
        ]);
        $this->assertGuest();
        $this->assertDatabaseMissing('activity_logs', [
            'module' => 'auth',
            'action' => 'login',
            'user_id' => $inactiveManager->id,
        ]);
    }

    public function test_purchase_user_login_redirects_to_purchases_and_logs_activity(): void
    {
        $purchaseUser = $this->createUser(User::ROLE_PURCHASE, true);

        $response = $this->post(route('login.post'), [
            'email' => $purchaseUser->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('purchases.index'));
        $this->assertAuthenticatedAs($purchaseUser);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'auth',
            'action' => 'login',
            'user_id' => $purchaseUser->id,
        ]);
    }

    public function test_owner_login_redirects_to_accounts_dashboard(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER, true);

        $response = $this->post(route('login.post'), [
            'email' => $owner->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('accounts.dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_manager_login_redirects_to_order_management(): void
    {
        $manager = $this->createUser(User::ROLE_MANAGER, true);

        $response = $this->post(route('login.post'), [
            'email' => $manager->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('orders.index'));
        $this->assertAuthenticatedAs($manager);
    }

    public function test_owner_is_redirected_to_accounts_dashboard_from_home(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER, true);

        $response = $this->actingAs($owner)->get('/');

        $response->assertRedirect(route('accounts.dashboard'));
    }

    public function test_logout_redirects_to_login_and_logs_activity(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER, true);

        $response = $this->actingAs($owner)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'auth',
            'action' => 'logout',
            'user_id' => $owner->id,
        ]);
    }

    private function createUser(string $role, bool $isActive): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => $isActive,
            'password' => 'password',
        ]);
    }
}

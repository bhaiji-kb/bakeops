<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_create_user_writes_activity_log(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER, true, 'owner@example.com');

        $response = $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'New Purchase User',
            'email' => 'new-purchase@example.com',
            'role' => User::ROLE_PURCHASE,
            'is_active' => 1,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('users.index'));
        $createdUser = User::query()->where('email', 'new-purchase@example.com')->first();
        $this->assertNotNull($createdUser);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'users',
            'action' => 'create',
            'entity_type' => User::class,
            'entity_id' => $createdUser->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_owner_update_user_writes_activity_log_with_old_and_new_values(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER, true, 'owner@example.com');
        $cashier = $this->createUser(User::ROLE_CASHIER, true, 'cashier@example.com');

        $response = $this->actingAs($owner)->put(route('users.update', ['user' => $cashier->id]), [
            'name' => 'Cashier Updated',
            'email' => $cashier->email,
            'role' => User::ROLE_MANAGER,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('users.index'));

        $log = ActivityLog::query()
            ->where('module', 'users')
            ->where('action', 'update')
            ->where('entity_type', User::class)
            ->where('entity_id', $cashier->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($owner->id, (int) $log->user_id);
        $this->assertSame(User::ROLE_CASHIER, $log->old_values['role']);
        $this->assertSame(User::ROLE_MANAGER, $log->new_values['role']);
        $this->assertSame('Cashier Updated', $log->new_values['name']);
    }

    public function test_owner_reset_password_writes_activity_log_and_updates_password(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER, true, 'owner@example.com');
        $target = $this->createUser(User::ROLE_MANAGER, true, 'manager@example.com', 'old-password');

        $response = $this->actingAs($owner)->post(route('users.reset_password', ['user' => $target->id]), [
            'password' => 'new-secret',
            'password_confirmation' => 'new-secret',
        ]);

        $response->assertRedirect();

        $target->refresh();
        $this->assertTrue(Hash::check('new-secret', $target->password));

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'users',
            'action' => 'reset_password',
            'entity_type' => User::class,
            'entity_id' => $target->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_owner_cannot_deactivate_self(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER, true, 'owner@example.com');
        $peerOwner = $this->createUser(User::ROLE_OWNER, true, 'peer-owner@example.com');

        $response = $this->actingAs($owner)
            ->from(route('users.edit', ['user' => $owner->id]))
            ->put(route('users.update', ['user' => $owner->id]), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => User::ROLE_OWNER,
                'is_active' => 0,
            ]);

        $response->assertRedirect(route('users.edit', ['user' => $owner->id]));
        $response->assertSessionHasErrors([
            'is_active' => 'You cannot deactivate your own account.',
        ]);

        $owner->refresh();
        $this->assertTrue((bool) $owner->is_active);
        $this->assertNull(
            ActivityLog::query()
                ->where('module', 'users')
                ->where('action', 'update')
                ->where('entity_id', $owner->id)
                ->first()
        );
        $this->assertTrue((bool) $peerOwner->fresh()->is_active);
    }

    public function test_cannot_demote_or_deactivate_last_active_owner(): void
    {
        $owner = $this->createUser(User::ROLE_OWNER, true, 'owner@example.com');

        $response = $this->actingAs($owner)
            ->from(route('users.edit', ['user' => $owner->id]))
            ->put(route('users.update', ['user' => $owner->id]), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => User::ROLE_MANAGER,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('users.edit', ['user' => $owner->id]));
        $response->assertSessionHasErrors([
            'role' => 'At least one active owner account is required.',
        ]);

        $owner->refresh();
        $this->assertSame(User::ROLE_OWNER, $owner->role);
        $this->assertTrue((bool) $owner->is_active);

        $this->assertNull(
            ActivityLog::query()
                ->where('module', 'users')
                ->where('action', 'update')
                ->where('entity_id', $owner->id)
                ->first()
        );
    }

    private function createUser(
        string $role,
        bool $isActive,
        string $email,
        string $password = 'password'
    ): User {
        return User::factory()->create([
            'name' => ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'role' => $role,
            'is_active' => $isActive,
            'password' => $password,
        ]);
    }
}


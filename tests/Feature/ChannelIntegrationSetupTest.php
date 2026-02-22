<?php

namespace Tests\Feature;

use App\Models\ChannelOrderEvent;
use App\Models\Customer;
use App\Models\IntegrationConnector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelIntegrationSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_configure_connectors_from_admin_panel(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->get(route('integrations.connectors.index'))->assertOk();

        $response = $this->actingAs($owner)->post(route('integrations.connectors.store'), [
            'code' => 'swiggy',
            'name' => 'Swiggy Outlet 1',
            'driver' => 'swiggy',
            'api_base_url' => 'https://partner.swiggy.com',
            'api_key' => 'test-api-key',
            'api_secret' => 'test-api-secret',
            'webhook_secret' => 'test-webhook-secret',
            'settings_json' => '{"outlet_id":"SWG-01","auto_accept":false}',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('integrations.connectors.index'));
        $this->assertDatabaseHas('integration_connectors', [
            'code' => 'swiggy',
            'name' => 'Swiggy Outlet 1',
            'driver' => 'swiggy',
            'is_active' => 1,
        ]);

        $connector = IntegrationConnector::query()->where('code', 'swiggy')->first();
        $this->assertNotNull($connector);
        $this->assertSame('test-api-key', $connector->api_key);
        $this->assertSame('test-api-secret', $connector->api_secret);
        $this->assertSame('test-webhook-secret', $connector->webhook_secret);
        $this->assertSame('SWG-01', $connector->settings['outlet_id'] ?? null);
    }

    public function test_non_owner_cannot_access_connector_admin_panel(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get(route('integrations.connectors.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('integrations.connectors.store'), [
            'code' => 'zomato',
            'name' => 'Zomato',
            'driver' => 'zomato',
            'is_active' => 1,
        ])->assertForbidden();
    }

    public function test_webhook_ingest_creates_channel_order_and_is_idempotent(): void
    {
        IntegrationConnector::create([
            'code' => 'swiggy',
            'name' => 'Swiggy',
            'driver' => 'swiggy',
            'is_active' => true,
        ]);

        $payload = [
            'event_id' => 'evt-001',
            'event_type' => 'order_placed',
            'order_id' => 'SWG-ORDER-101',
            'status' => 'placed',
            'total_amount' => 459.50,
            'customer' => [
                'name' => 'Aditi',
                'mobile' => '9998887776',
                'address_line1' => 'Flat 201',
                'road' => 'Lake View Road',
                'sector' => 'Sector 3',
                'city' => 'Pune',
                'pincode' => '411001',
                'preference' => 'No onion garlic',
            ],
        ];

        $first = $this->withHeaders([
            'X-Request-Id' => 'req-001',
        ])->postJson(route('integrations.webhook.receive', ['channel' => 'swiggy']), $payload);

        $first->assertStatus(202)->assertJson([
            'duplicate' => false,
        ]);

        $this->assertDatabaseHas('channel_orders', [
            'channel' => 'swiggy',
            'external_order_id' => 'SWG-ORDER-101',
            'status' => 'placed',
        ]);
        $this->assertDatabaseHas('channel_order_events', [
            'channel' => 'swiggy',
            'external_order_id' => 'SWG-ORDER-101',
            'idempotency_key' => 'swiggy:evt-001',
        ]);
        $this->assertDatabaseHas('customers', [
            'mobile' => '9998887776',
            'name' => 'Aditi',
            'address_line1' => 'Flat 201',
            'road' => 'Lake View Road',
            'sector' => 'Sector 3',
            'city' => 'Pune',
            'pincode' => '411001',
            'preference' => 'No onion garlic',
        ]);
        $customer = Customer::query()->where('mobile', '9998887776')->first();
        $this->assertNotNull($customer);
        $this->assertSame('Aditi', $customer->name);

        $duplicate = $this->withHeaders([
            'X-Request-Id' => 'req-001',
        ])->postJson(route('integrations.webhook.receive', ['channel' => 'swiggy']), $payload);

        $duplicate->assertOk()->assertJson([
            'duplicate' => true,
        ]);
        $this->assertSame(1, ChannelOrderEvent::query()->count());
    }

    public function test_webhook_rejects_unknown_or_inactive_connector(): void
    {
        $this->postJson(route('integrations.webhook.receive', ['channel' => 'zomato']), [
            'order_id' => 'Z-100',
            'status' => 'placed',
        ])->assertStatus(404);

        IntegrationConnector::create([
            'code' => 'zomato',
            'name' => 'Zomato',
            'driver' => 'zomato',
            'is_active' => false,
        ]);

        $this->postJson(route('integrations.webhook.receive', ['channel' => 'zomato']), [
            'order_id' => 'Z-101',
            'status' => 'placed',
        ])->assertStatus(404);
    }
}

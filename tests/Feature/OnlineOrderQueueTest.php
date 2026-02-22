<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderEvent;
use App\Models\IntegrationConnector;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnlineOrderQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_controls_are_muted_when_no_active_connectors_configured(): void
    {
        $cashier = User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'is_active' => true,
        ]);

        ChannelOrder::create([
            'channel' => 'swiggy',
            'external_order_id' => 'SWG-MUTED-1',
            'status' => 'placed',
        ]);

        $response = $this->actingAs($cashier)->get(route('pos.online_orders.index'));

        $response->assertOk();
        $response->assertSee('Queue controls are muted', false);
        $response->assertSee('btn btn-sm btn-outline-dark disabled', false);
    }

    public function test_channel_filter_options_follow_active_connector_config(): void
    {
        $cashier = User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'is_active' => true,
        ]);

        IntegrationConnector::create([
            'code' => 'swiggy',
            'name' => 'Swiggy',
            'driver' => 'swiggy',
            'is_active' => true,
        ]);
        IntegrationConnector::create([
            'code' => 'zomato',
            'name' => 'Zomato',
            'driver' => 'zomato',
            'is_active' => false,
        ]);

        $response = $this->actingAs($cashier)->get(route('pos.online_orders.index'));

        $response->assertOk();
        $response->assertSee('SWIGGY', false);
        $response->assertDontSee('ZOMATO', false);
    }

    public function test_cashier_can_accept_online_order_and_sale_status_syncs(): void
    {
        $cashier = User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'is_active' => true,
        ]);

        $connector = IntegrationConnector::create([
            'code' => 'swiggy',
            'name' => 'Swiggy',
            'driver' => 'swiggy',
            'api_base_url' => 'https://connector.test',
            'api_key' => 'api-key-1',
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'bill_number' => 'INV-20260222-00001',
            'total_amount' => 350,
            'payment_mode' => 'cash',
            'sub_total' => 350,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'paid_amount' => 350,
            'balance_amount' => 0,
            'channel' => 'swiggy',
            'external_order_id' => 'SWG-1001',
            'channel_status' => 'placed',
        ]);

        $order = ChannelOrder::create([
            'integration_connector_id' => $connector->id,
            'sale_id' => $sale->id,
            'channel' => 'swiggy',
            'external_order_id' => 'SWG-1001',
            'status' => 'placed',
        ]);

        Http::fake([
            'https://connector.test/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->actingAs($cashier)
            ->from(route('pos.online_orders.index'))
            ->post(route('pos.online_orders.accept', ['channelOrder' => $order->id]));

        $response->assertRedirect(route('pos.online_orders.index'));

        $order->refresh();
        $sale->refresh();
        $this->assertSame('accepted', $order->status);
        $this->assertNotNull($order->accepted_at);
        $this->assertSame('accepted', $sale->channel_status);
        $this->assertNotNull($sale->channel_accepted_at);
        $this->assertNotNull($order->order_id);
        $this->assertDatabaseHas('orders', [
            'id' => $order->order_id,
            'sale_id' => $sale->id,
            'source' => 'swiggy',
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('kots', [
            'order_id' => $order->order_id,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('channel_order_events', [
            'channel_order_id' => $order->id,
            'event_type' => 'outbound_accept',
            'process_status' => 'processed',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://connector.test/orders/SWG-1001/accept'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_cashier_can_reject_and_mark_ready_online_order(): void
    {
        $cashier = User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'is_active' => true,
        ]);

        $connector = IntegrationConnector::create([
            'code' => 'zomato',
            'name' => 'Zomato',
            'driver' => 'zomato',
            'api_base_url' => 'https://connector.test',
            'api_key' => 'api-key-2',
            'is_active' => true,
            'settings' => [
                'actions' => [
                    'reject' => [
                        'path' => '/v2/orders/{external_order_id}/reject',
                        'body' => ['reason' => '{reason}'],
                    ],
                    'ready' => [
                        'path' => '/v2/orders/{external_order_id}/ready',
                    ],
                ],
            ],
        ]);

        $rejectOrder = ChannelOrder::create([
            'integration_connector_id' => $connector->id,
            'channel' => 'zomato',
            'external_order_id' => 'ZMT-2001',
            'status' => 'placed',
        ]);

        $readyOrder = ChannelOrder::create([
            'integration_connector_id' => $connector->id,
            'channel' => 'zomato',
            'external_order_id' => 'ZMT-2002',
            'status' => 'accepted',
        ]);

        Http::fake([
            'https://connector.test/*' => Http::response(['ok' => true], 200),
        ]);

        $rejectResponse = $this->actingAs($cashier)
            ->from(route('pos.online_orders.index'))
            ->post(route('pos.online_orders.reject', ['channelOrder' => $rejectOrder->id]), [
                'reason' => 'Out of stock',
            ]);

        $rejectResponse->assertRedirect(route('pos.online_orders.index'));
        $rejectOrder->refresh();
        $this->assertSame('rejected', $rejectOrder->status);
        $this->assertNotNull($rejectOrder->cancelled_at);

        $readyResponse = $this->actingAs($cashier)
            ->from(route('pos.online_orders.index'))
            ->post(route('pos.online_orders.ready', ['channelOrder' => $readyOrder->id]));

        $readyResponse->assertRedirect(route('pos.online_orders.index'));
        $readyOrder->refresh();
        $this->assertSame('ready', $readyOrder->status);
        $this->assertNotNull($readyOrder->ready_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://connector.test/v2/orders/ZMT-2001/reject'
                && $request['reason'] === 'Out of stock';
        });
        Http::assertSent(function ($request) {
            return $request->url() === 'https://connector.test/v2/orders/ZMT-2002/ready'
                && $request->method() === 'POST';
        });
    }

    public function test_action_failure_keeps_status_and_logs_failed_event(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $connector = IntegrationConnector::create([
            'code' => 'swiggy',
            'name' => 'Swiggy',
            'driver' => 'swiggy',
            'api_base_url' => 'https://connector.test',
            'api_key' => 'api-key-3',
            'is_active' => true,
        ]);

        $order = ChannelOrder::create([
            'integration_connector_id' => $connector->id,
            'channel' => 'swiggy',
            'external_order_id' => 'SWG-FAIL-1',
            'status' => 'placed',
        ]);

        Http::fake([
            'https://connector.test/*' => Http::response(['error' => 'bad request'], 422),
        ]);

        $response = $this->actingAs($manager)
            ->from(route('pos.online_orders.index'))
            ->post(route('pos.online_orders.accept', ['channelOrder' => $order->id]));

        $response->assertRedirect(route('pos.online_orders.index'));
        $response->assertSessionHasErrors(['online_order']);

        $order->refresh();
        $this->assertSame('placed', $order->status);

        $failedEvent = ChannelOrderEvent::query()
            ->where('channel_order_id', $order->id)
            ->where('event_type', 'outbound_accept')
            ->latest('id')
            ->first();
        $this->assertNotNull($failedEvent);
        $this->assertSame('failed', $failedEvent->process_status);
    }

    public function test_accept_does_not_create_invoice_for_online_order_when_sale_is_missing_by_default(): void
    {
        $cashier = User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'is_active' => true,
        ]);

        $connector = IntegrationConnector::create([
            'code' => 'swiggy',
            'name' => 'Swiggy',
            'driver' => 'swiggy',
            'api_base_url' => 'https://connector.test',
            'api_key' => 'api-key-4',
            'is_active' => true,
        ]);

        $order = ChannelOrder::create([
            'integration_connector_id' => $connector->id,
            'channel' => 'swiggy',
            'external_order_id' => 'SWG-NEW-501',
            'customer_name' => 'Ankita',
            'customer_identifier' => '9988776655',
            'order_total' => 540,
            'status' => 'placed',
            'latest_payload' => [
                'delivery_address' => 'Pine Street',
            ],
        ]);

        Http::fake([
            'https://connector.test/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->actingAs($cashier)
            ->from(route('pos.online_orders.index'))
            ->post(route('pos.online_orders.accept', ['channelOrder' => $order->id]));

        $response->assertRedirect(route('pos.online_orders.index'));
        $response->assertSessionMissing('invoice_sale_id');

        $order->refresh();
        $this->assertNull($order->sale_id);
        $this->assertNotNull($order->order_id);
        $this->assertDatabaseHas('orders', [
            'id' => $order->order_id,
            'source' => 'swiggy',
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('kots', [
            'order_id' => $order->order_id,
            'status' => 'open',
        ]);
        $this->assertDatabaseCount('sales', 0);
    }
}

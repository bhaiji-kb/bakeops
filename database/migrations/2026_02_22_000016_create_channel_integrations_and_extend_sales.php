<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_connectors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('driver', 40)->default('generic');
            $table->string('api_base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('channel_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_connector_id')->nullable()->constrained('integration_connectors')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('channel', 30);
            $table->string('external_order_id', 100);
            $table->string('customer_name')->nullable();
            $table->string('customer_identifier', 40)->nullable();
            $table->decimal('order_total', 10, 2)->nullable();
            $table->string('status', 30)->default('received');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->longText('latest_payload')->nullable();
            $table->longText('normalized_payload')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['channel', 'external_order_id']);
            $table->index(['status', 'last_event_at']);
        });

        Schema::create('channel_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->foreignId('integration_connector_id')->nullable()->constrained('integration_connectors')->nullOnDelete();
            $table->string('channel', 30);
            $table->string('external_order_id', 100)->nullable();
            $table->string('external_event_id', 120)->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->string('event_type', 60)->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->longText('payload')->nullable();
            $table->longText('normalized_payload')->nullable();
            $table->string('process_status', 30)->default('received');
            $table->string('process_error', 500)->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'external_order_id']);
            $table->index(['process_status', 'created_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('channel', 30)->nullable()->after('order_source');
            $table->string('external_order_id', 100)->nullable()->after('channel');
            $table->string('channel_status', 30)->nullable()->after('external_order_id');
            $table->timestamp('channel_accepted_at')->nullable()->after('channel_status');
            $table->timestamp('channel_ready_at')->nullable()->after('channel_accepted_at');
            $table->timestamp('channel_delivered_at')->nullable()->after('channel_ready_at');
            $table->timestamp('channel_cancelled_at')->nullable()->after('channel_delivered_at');

            $table->index('channel');
            $table->index('external_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropIndex(['external_order_id']);
            $table->dropColumn([
                'channel',
                'external_order_id',
                'channel_status',
                'channel_accepted_at',
                'channel_ready_at',
                'channel_delivered_at',
                'channel_cancelled_at',
            ]);
        });

        Schema::dropIfExists('channel_order_events');
        Schema::dropIfExists('channel_orders');
        Schema::dropIfExists('integration_connectors');
    }
};

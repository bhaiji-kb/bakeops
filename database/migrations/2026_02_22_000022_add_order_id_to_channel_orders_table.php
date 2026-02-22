<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_orders', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('sale_id')
                ->constrained('orders')
                ->nullOnDelete();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('channel_orders', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropConstrainedForeignId('order_id');
        });
    }
};

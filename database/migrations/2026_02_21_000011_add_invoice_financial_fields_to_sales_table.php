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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('sub_total', 10, 2)->default(0)->after('bill_number');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('sub_total');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('discount_amount');
            $table->decimal('round_off', 10, 2)->default(0)->after('tax_amount');
            $table->decimal('paid_amount', 10, 2)->default(0)->after('payment_mode');
            $table->decimal('balance_amount', 10, 2)->default(0)->after('paid_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'sub_total',
                'discount_amount',
                'tax_amount',
                'round_off',
                'paid_amount',
                'balance_amount',
            ]);
        });
    }
};


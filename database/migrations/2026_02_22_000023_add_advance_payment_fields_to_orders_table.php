<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('advance_paid_amount', 10, 2)->default(0)->after('notes');
            $table->string('advance_payment_mode', 20)->nullable()->after('advance_paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'advance_paid_amount',
                'advance_payment_mode',
            ]);
        });
    }
};

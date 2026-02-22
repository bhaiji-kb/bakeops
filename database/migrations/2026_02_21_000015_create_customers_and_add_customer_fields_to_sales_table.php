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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile', 20)->nullable()->unique();
            $table->string('identifier', 40)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained('customers')->nullOnDelete();
            $table->string('customer_identifier', 40)->nullable()->after('customer_id');
            $table->string('customer_name_snapshot')->nullable()->after('customer_identifier');
            $table->string('order_source', 30)->default('outlet')->after('payment_mode');
            $table->string('order_reference', 60)->nullable()->after('order_source');

            $table->index('customer_identifier');
            $table->index('order_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['customer_identifier']);
            $table->dropIndex(['order_source']);
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn([
                'customer_identifier',
                'customer_name_snapshot',
                'order_source',
                'order_reference',
            ]);
        });

        Schema::dropIfExists('customers');
    }
};

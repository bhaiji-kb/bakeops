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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 4)->default(0)->after('price');
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->decimal('total_ingredient_cost', 12, 2)->default(0)->after('quantity_produced');
            $table->decimal('unit_production_cost', 12, 4)->default(0)->after('total_ingredient_cost');
        });

        Schema::table('production_batch_items', function (Blueprint $table) {
            $table->decimal('ingredient_unit_cost', 12, 4)->default(0)->after('quantity_used');
            $table->decimal('total_cost', 12, 2)->default(0)->after('ingredient_unit_cost');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 4)->default(0)->after('price');
            $table->decimal('cost_total', 12, 2)->default(0)->after('total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'cost_total']);
        });

        Schema::table('production_batch_items', function (Blueprint $table) {
            $table->dropColumn(['ingredient_unit_cost', 'total_cost']);
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropColumn(['total_ingredient_cost', 'unit_production_cost']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};


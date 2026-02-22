<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $products = DB::table('products')->orderBy('id')->pluck('id');
        if ($products->count() > 99) {
            throw new RuntimeException('Two-digit product codes support up to 99 products.');
        }

        foreach ($products as $index => $productId) {
            DB::table('products')
                ->where('id', $productId)
                ->update(['code' => sprintf('%02d', $index + 1)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $products = DB::table('products')->orderBy('id')->pluck('id');
        foreach ($products as $productId) {
            DB::table('products')
                ->where('id', $productId)
                ->update(['code' => sprintf('P%05d', (int) $productId)]);
        }
    }
};

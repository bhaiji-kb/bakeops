<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->after('name');
        });

        $ids = DB::table('products')->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            DB::table('products')
                ->where('id', $id)
                ->whereNull('code')
                ->update(['code' => sprintf('P%05d', (int) $id)]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_code_unique');
            $table->dropColumn('code');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'address_line1')) {
                $table->string('address_line1', 255)->nullable()->after('address');
            }
            if (!Schema::hasColumn('customers', 'road')) {
                $table->string('road', 255)->nullable()->after('address_line1');
            }
            if (!Schema::hasColumn('customers', 'sector')) {
                $table->string('sector', 120)->nullable()->after('road');
            }
            if (!Schema::hasColumn('customers', 'city')) {
                $table->string('city', 120)->nullable()->after('sector');
            }
            if (!Schema::hasColumn('customers', 'pincode')) {
                $table->string('pincode', 10)->nullable()->after('city');
            }
            if (!Schema::hasColumn('customers', 'preference')) {
                $table->string('preference', 255)->nullable()->after('pincode');
            }
        });

        DB::table('customers')
            ->whereNull('address_line1')
            ->whereNotNull('address')
            ->update([
                'address_line1' => DB::raw('address'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['address_line1', 'road', 'sector', 'city', 'pincode', 'preference'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

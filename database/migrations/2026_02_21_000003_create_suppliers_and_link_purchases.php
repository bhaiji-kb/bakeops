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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('id')->constrained('suppliers')->nullOnDelete();
        });

        $names = DB::table('purchases')
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '!=', '')
            ->select('supplier_name')
            ->distinct()
            ->pluck('supplier_name');

        foreach ($names as $name) {
            $rawName = (string) $name;
            $supplierName = trim($rawName);
            if ($supplierName === '') {
                continue;
            }

            $supplierId = DB::table('suppliers')
                ->where('name', $supplierName)
                ->value('id');

            if (!$supplierId) {
                $supplierId = DB::table('suppliers')->insertGetId([
                    'name' => $supplierName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('purchases')
                ->where('supplier_name', $rawName)
                ->update(['supplier_id' => $supplierId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::dropIfExists('suppliers');
    }
};

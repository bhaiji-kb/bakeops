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
            $table->string('legacy_code', 20)->nullable()->after('code');
            $table->unique('legacy_code');
        });

        $products = DB::table('products')
            ->select('id', 'type', 'code')
            ->orderBy('type')
            ->orderBy('id')
            ->get();

        $counters = [
            'finished_good' => 0,
            'raw_material' => 0,
            'other' => 0,
        ];

        foreach ($products as $product) {
            $type = (string) $product->type;
            $legacyCode = trim((string) $product->code);

            if ($type === 'finished_good') {
                $counters['finished_good']++;
                $newCode = sprintf('FG%03d', $counters['finished_good']);
            } elseif ($type === 'raw_material') {
                $counters['raw_material']++;
                $newCode = sprintf('RM%03d', $counters['raw_material']);
            } else {
                $counters['other']++;
                $newCode = sprintf('PR%03d', $counters['other']);
            }

            DB::table('products')
                ->where('id', $product->id)
                ->update([
                    'legacy_code' => $legacyCode !== '' ? $legacyCode : null,
                    'code' => $newCode,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $products = DB::table('products')
            ->select('id', 'code', 'legacy_code')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $fallbackLegacy = trim((string) $product->legacy_code);
            if ($fallbackLegacy !== '') {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['code' => $fallbackLegacy]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_legacy_code_unique');
            $table->dropColumn('legacy_code');
        });
    }
};

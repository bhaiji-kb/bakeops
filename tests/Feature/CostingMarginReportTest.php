<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostingMarginReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_costing_updates_finished_unit_cost_and_impacts_sales_and_pl_reports(): void
    {
        Carbon::setTestNow('2026-02-21 09:00:00');
        try {
            $manager = User::factory()->create([
                'role' => User::ROLE_MANAGER,
                'is_active' => true,
            ]);

            $finished = Product::create([
                'name' => 'Chocolate Truffle',
                'type' => 'finished_good',
                'unit' => 'pcs',
                'reorder_level' => 1,
                'price' => 200,
                'is_active' => true,
            ]);
            $ingredient = Product::create([
                'name' => 'Cocoa Mix',
                'type' => 'raw_material',
                'unit' => 'kg',
                'reorder_level' => 1,
                'price' => 50,
                'is_active' => true,
            ]);

            RecipeItem::create([
                'finished_product_id' => $finished->id,
                'ingredient_product_id' => $ingredient->id,
                'quantity' => 1,
            ]);

            InventoryTransaction::create([
                'product_id' => $ingredient->id,
                'quantity' => 10,
                'transaction_type' => 'IN',
                'reference_type' => 'seed',
                'reference_id' => 1,
                'notes' => 'Initial stock',
            ]);

            $this->actingAs($manager)->post(route('production.store'), [
                'finished_product_id' => $finished->id,
                'quantity_produced' => 3,
                'produced_at' => '2026-02-21 08:00:00',
                'notes' => 'Morning batch',
            ])->assertRedirect(route('production.index', [
                'product_id' => $finished->id,
                'quantity' => 3.0,
            ]));

            $finished->refresh();
            $this->assertEquals(50.0, (float) $finished->unit_cost);

            $this->actingAs($manager)->post(route('pos.checkout'), [
                'items' => [
                    ['product_id' => $finished->id, 'quantity' => 2],
                ],
                'payment_mode' => 'cash',
            ])->assertRedirect();

            $saleItem = SaleItem::query()->first();
            $this->assertNotNull($saleItem);
            $this->assertEquals(50.0, (float) $saleItem->unit_cost);
            $this->assertEquals(100.0, (float) $saleItem->cost_total);

            Expense::create([
                'expense_date' => '2026-02-21',
                'category' => 'emi',
                'amount' => 120,
                'notes' => 'EMI',
            ]);

            $salesReport = $this->actingAs($manager)->get(route('reports.sales.daily', ['date' => '2026-02-21']));
            $salesReport->assertOk();
            $salesReport->assertViewHas('totalSales', fn ($v) => abs((float) $v - 400.0) < 0.01);
            $salesReport->assertViewHas('totalCogs', fn ($v) => abs((float) $v - 100.0) < 0.01);
            $salesReport->assertViewHas('grossProfit', fn ($v) => abs((float) $v - 300.0) < 0.01);

            $plReport = $this->actingAs($manager)->get(route('reports.profit_loss.monthly', ['month' => '2026-02']));
            $plReport->assertOk();
            $plReport->assertViewHas('totalSales', fn ($v) => abs((float) $v - 400.0) < 0.01);
            $plReport->assertViewHas('totalCogs', fn ($v) => abs((float) $v - 100.0) < 0.01);
            $plReport->assertViewHas('grossProfit', fn ($v) => abs((float) $v - 300.0) < 0.01);
            $plReport->assertViewHas('totalExpense', fn ($v) => abs((float) $v - 120.0) < 0.01);
            $plReport->assertViewHas('netProfit', fn ($v) => abs((float) $v - 180.0) < 0.01);
        } finally {
            Carbon::setTestNow();
        }
    }
}

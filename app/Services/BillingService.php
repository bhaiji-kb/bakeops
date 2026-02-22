<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public function createSale(array $items, string $paymentMode, array $financials = [], array $context = []): Sale
    {
        return DB::transaction(function () use ($items, $paymentMode, $financials, $context) {
            $discountAmount = round((float) ($financials['discount_amount'] ?? 0), 2);
            $taxAmount = round((float) ($financials['tax_amount'] ?? 0), 2);
            $roundOff = round((float) ($financials['round_off'] ?? 0), 2);
            $paidInput = $financials['paid_amount'] ?? null;
            $orderSource = (string) ($context['order_source'] ?? 'outlet');
            $channel = $context['channel'] ?? null;
            $orderReference = $context['order_reference'] ?? null;
            $customerId = $context['customer_id'] ?? null;
            $createdByUserId = $context['created_by_user_id'] ?? null;
            $customerIdentifier = $context['customer_identifier'] ?? null;
            $customerNameSnapshot = $context['customer_name_snapshot'] ?? null;
            $customerAddressSnapshot = $context['customer_address_snapshot'] ?? null;
            $externalOrderId = $context['external_order_id'] ?? null;
            $channelStatus = $context['channel_status'] ?? null;
            $channelAcceptedAt = $context['channel_accepted_at'] ?? null;
            $channelReadyAt = $context['channel_ready_at'] ?? null;
            $channelDeliveredAt = $context['channel_delivered_at'] ?? null;
            $channelCancelledAt = $context['channel_cancelled_at'] ?? null;

            if ($channel === null && in_array($orderSource, ['swiggy', 'zomato'], true)) {
                $channel = $orderSource;
            }

            $temporaryBillNumber = 'TMP-' . Str::uuid()->toString();

            $sale = Sale::create([
                'bill_number' => $temporaryBillNumber,
                'customer_id' => $customerId,
                'created_by_user_id' => $createdByUserId,
                'customer_identifier' => $customerIdentifier,
                'customer_name_snapshot' => $customerNameSnapshot,
                'customer_address_snapshot' => $customerAddressSnapshot,
                'sub_total' => 0,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'round_off' => $roundOff,
                'total_amount' => 0,
                'payment_mode' => $paymentMode,
                'order_source' => $orderSource,
                'channel' => $channel,
                'external_order_id' => $externalOrderId,
                'channel_status' => $channelStatus,
                'channel_accepted_at' => $channelAcceptedAt,
                'channel_ready_at' => $channelReadyAt,
                'channel_delivered_at' => $channelDeliveredAt,
                'channel_cancelled_at' => $channelCancelledAt,
                'order_reference' => $orderReference,
                'paid_amount' => 0,
                'balance_amount' => 0,
            ]);

            $inventory = new InventoryService();
            $subTotal = 0;
            $hasAtLeastOneItem = false;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                $quantity = (float) $item['quantity'];
                if ($quantity <= 0) {
                    continue;
                }
                $hasAtLeastOneItem = true;

                $lineTotal = round($quantity * (float) $product->price, 2);
                $unitCost = round((float) $product->unit_cost, 4);
                $costTotal = round($quantity * $unitCost, 2);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $product->price,
                    'unit_cost' => $unitCost,
                    'total' => $lineTotal,
                    'cost_total' => $costTotal,
                ]);

                $inventory->deductStock(
                    $product,
                    $quantity,
                    'sale',
                    $sale->id
                );

                $subTotal += $lineTotal;
            }

            if (!$hasAtLeastOneItem) {
                throw new \Exception('Add at least one item quantity greater than 0.');
            }

            $netTotal = round($subTotal - $discountAmount + $taxAmount + $roundOff, 2);
            if ($netTotal < 0) {
                throw new \Exception('Final total cannot be negative after discount/tax/round-off.');
            }

            $paidAmount = $paidInput === null || $paidInput === '' ? $netTotal : round((float) $paidInput, 2);
            if ($paidAmount < 0) {
                throw new \Exception('Paid amount cannot be negative.');
            }
            if ($paidAmount > $netTotal) {
                throw new \Exception('Paid amount cannot exceed final total.');
            }
            $balanceAmount = round($netTotal - $paidAmount, 2);

            $finalBillNumber = sprintf(
                'INV-%s-%05d',
                now()->format('Ymd'),
                (int) $sale->id
            );

            $sale->update([
                'bill_number' => $finalBillNumber,
                'customer_id' => $customerId,
                'created_by_user_id' => $createdByUserId,
                'customer_identifier' => $customerIdentifier,
                'customer_name_snapshot' => $customerNameSnapshot,
                'customer_address_snapshot' => $customerAddressSnapshot,
                'sub_total' => round($subTotal, 2),
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'round_off' => $roundOff,
                'total_amount' => $netTotal,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'order_source' => $orderSource,
                'channel' => $channel,
                'external_order_id' => $externalOrderId,
                'channel_status' => $channelStatus,
                'channel_accepted_at' => $channelAcceptedAt,
                'channel_ready_at' => $channelReadyAt,
                'channel_delivered_at' => $channelDeliveredAt,
                'channel_cancelled_at' => $channelCancelledAt,
                'order_reference' => $orderReference,
            ]);

            return $sale->fresh(['items', 'customer', 'createdBy']);
        });
    }
}

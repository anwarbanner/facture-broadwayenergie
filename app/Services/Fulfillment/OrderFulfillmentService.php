<?php

namespace App\Services\Fulfillment;

use App\Models\DeliveryItem;
use App\Models\DeliveryNoteItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\Stock\StockService;
use Illuminate\Support\Collection;

/**
 * Partial deliveries and receipts.
 *
 * Each delivery / receipt line points to its order line (sales_order_item_id / purchase_order_item_id).
 * - delivered_qty on an order line = quantities that physically moved (shipped / received).
 * - remaining = ordered - already delivered or planned in open documents; a new delivery / receipt
 *   created from the order only takes what remains.
 * - the order status follows: nothing moved -> confirmed, some -> partial, everything -> delivered.
 */
class OrderFulfillmentService
{
    private const EPSILON = 0.0005;

    // ---- Sales -------------------------------------------------------------------------------------

    /** @return Collection<int, array{item: SalesOrderItem, remaining: float}> lines that still need a delivery */
    public function remainingToDeliver(SalesOrder $order): Collection
    {
        return $order->items()->get()
            ->map(function (SalesOrderItem $item) {
                // Pending deliveries count as planned so the same quantity is never delivered twice.
                $planned = (float) DeliveryItem::where('sales_order_item_id', $item->id)
                    ->whereHas('delivery', fn ($q) => $q->whereIn('status', ['pending', 'shipped', 'delivered']))
                    ->sum('shipped_qty');

                return ['item' => $item, 'remaining' => round((float) $item->quantity - $planned, 3)];
            })
            ->filter(fn ($line) => $line['remaining'] > self::EPSILON)
            ->values();
    }

    public function syncSalesOrder(?SalesOrder $order): void
    {
        if (!$order) {
            return;
        }

        $items = $order->items()->get();
        foreach ($items as $item) {
            $item->delivered_qty = round((float) DeliveryItem::where('sales_order_item_id', $item->id)
                ->whereHas('delivery', fn ($q) => $q->whereIn('status', StockService::DELIVERY_OUT_STATUSES))
                ->sum('shipped_qty'), 3);
            $item->save();
        }

        $this->applyStatus($order, $items, $order->deliveries()->exists());
    }

    /**
     * Link a delivery line to its order line: keep an explicit, valid link; otherwise match the first
     * order line with the same product / service that is not used yet on this delivery.
     */
    public function salesSourceFor(?SalesOrder $order, ?int $sourceId, ?int $productId, ?int $serviceId, array $usedIds, ?string $itemName = null): ?int
    {
        if (!$order) {
            return null;
        }

        $items = $order->items()->get();
        if ($sourceId && $items->contains('id', $sourceId)) {
            return $sourceId;
        }

        return $items->first(fn ($item) => !in_array($item->id, $usedIds, true)
            && (($productId && (int) $item->product_id === $productId)
                || ($serviceId && (int) $item->service_id === $serviceId)
                || (!$productId && !$serviceId && $itemName !== null && $item->item_name === $itemName)))?->id;
    }

    // ---- Purchases ---------------------------------------------------------------------------------

    /** @return Collection<int, array{item: PurchaseOrderItem, remaining: float}> lines not fully received yet */
    public function remainingToReceive(PurchaseOrder $order): Collection
    {
        return $order->items()->get()
            ->map(function (PurchaseOrderItem $item) {
                $received = (float) DeliveryNoteItem::where('purchase_order_item_id', $item->id)
                    ->whereHas('deliveryNote')
                    ->sum('received_qty');

                return ['item' => $item, 'remaining' => round((float) $item->quantity - $received, 3)];
            })
            ->filter(fn ($line) => $line['remaining'] > self::EPSILON)
            ->values();
    }

    public function syncPurchaseOrder(?PurchaseOrder $order): void
    {
        if (!$order) {
            return;
        }

        $items = $order->items()->get();
        foreach ($items as $item) {
            $item->delivered_qty = round((float) DeliveryNoteItem::where('purchase_order_item_id', $item->id)
                ->whereHas('deliveryNote', fn ($q) => $q->whereIn('status', StockService::RECEIPT_IN_STATUSES))
                ->sum('received_qty'), 3);
            $item->save();
        }

        $this->applyStatus($order, $items, $order->deliveryNotes()->exists());
    }

    public function purchaseSourceFor(?PurchaseOrder $order, ?int $sourceId, ?int $productId, ?int $serviceId, ?string $itemName, array $usedIds): ?int
    {
        if (!$order) {
            return null;
        }

        $items = $order->items()->get();
        if ($sourceId && $items->contains('id', $sourceId)) {
            return $sourceId;
        }

        return $items->first(fn ($item) => !in_array($item->id, $usedIds, true)
            && (($productId && (int) $item->product_id === $productId)
                || ($serviceId && (int) $item->service_id === $serviceId)
                || (!$productId && !$serviceId && $itemName !== null && $item->item_name === $itemName)))?->id;
    }

    // ---- Shared ------------------------------------------------------------------------------------

    private function applyStatus(SalesOrder|PurchaseOrder $order, Collection $items, bool $hasDocuments): void
    {
        if (in_array($order->status, ['draft', 'cancelled'], true) && !$hasDocuments) {
            return;
        }

        $totalOrdered = (float) $items->sum('quantity');
        $totalDone = (float) $items->sum(fn ($item) => min((float) $item->delivered_qty, (float) $item->quantity));

        $status = match (true) {
            $totalOrdered > 0 && $totalDone >= $totalOrdered - self::EPSILON => 'delivered',
            $totalDone > self::EPSILON || $hasDocuments => 'partial',
            default => 'confirmed',
        };

        if ($order->status !== $status) {
            $order->forceFill(['status' => $status])->save();
        }
    }
}

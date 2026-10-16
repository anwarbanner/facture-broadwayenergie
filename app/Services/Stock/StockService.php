<?php

namespace App\Services\Stock;

use App\Events\ProductStockChanged;
use App\Models\Delivery;
use App\Models\DeliveryNote;
use App\Models\Products;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single entry point for every stock change.
 *
 * Rules:
 * - Only a client delivery (out) or a supplier receipt (in) moves physical stock. Invoices never do.
 * - Every change writes a StockMovement; movements are never deleted. Editing, cancelling or
 *   deleting a document appends a corrective movement for the difference.
 * - Products are locked (SELECT ... FOR UPDATE) and everything runs in a transaction.
 */
class StockService
{
    /** Delivery statuses where goods have physically left the warehouse. */
    public const DELIVERY_OUT_STATUSES = ['shipped', 'delivered'];

    /** Receipt statuses where received quantities are in the warehouse. */
    public const RECEIPT_IN_STATUSES = ['partial', 'complete'];

    private const EPSILON = 0.0005;

    public function syncDelivery(Delivery $delivery, bool $deleting = false): void
    {
        $quantities = [];
        // A BL made from a quote (simple mode) is only printed: moves_stock = false, nothing ever leaves the stock.
        if (!$deleting && $delivery->moves_stock !== false && in_array($delivery->status, self::DELIVERY_OUT_STATUSES, true)) {
            foreach ($delivery->items()->get() as $item) {
                if ($item->product_id) {
                    $quantities[$item->product_id] = ($quantities[$item->product_id] ?? 0) + (float) $item->shipped_qty;
                }
            }
        }

        $this->syncDocument(
            self::deliveryReference($delivery),
            'out',
            $quantities,
            'Livraison client ' . $delivery->delivery_number
        );
    }

    public function syncReceipt(DeliveryNote $note, bool $deleting = false): void
    {
        $quantities = [];
        if (!$deleting && in_array($note->status, self::RECEIPT_IN_STATUSES, true)) {
            foreach ($note->items()->get() as $item) {
                if ($item->product_id) {
                    $quantities[$item->product_id] = ($quantities[$item->product_id] ?? 0) + (float) $item->received_qty;
                }
            }
        }

        $this->syncDocument(
            self::receiptReference($note),
            'in',
            $quantities,
            'Reception fournisseur ' . $note->receipt_number
        );
    }

    /**
     * Manual correction from the product screen: brings the product to $targetQuantity.
     */
    public function adjustTo(Products $product, float $targetQuantity, string $reference, string $reason): void
    {
        DB::transaction(function () use ($product, $targetQuantity, $reference, $reason) {
            $locked = $this->lockProduct($product->id);
            $delta = round($targetQuantity - (float) $locked->stock_quantity, 3);

            if (abs($delta) >= self::EPSILON) {
                $this->move($locked, $delta, $reference, $reason);
            }
        });

        $product->refresh();
    }

    /**
     * Makes the net stock effect of $reference equal to $quantities (product_id => qty, positive,
     * in the document's $direction) by appending corrective movements for the difference.
     */
    public function syncDocument(string $reference, string $direction, array $quantities, string $reason): void
    {
        $sign = $direction === 'in' ? 1 : -1;

        DB::transaction(function () use ($reference, $sign, $quantities, $reason) {
            $applied = [];
            foreach (StockMovement::where('reference_document', $reference)->get() as $movement) {
                $signed = ($movement->movement_type === 'out' ? -1 : 1) * (float) $movement->quantity;
                $applied[$movement->product_id] = ($applied[$movement->product_id] ?? 0) + $signed;
            }

            $productIds = array_unique(array_merge(array_keys($applied), array_keys($quantities)));
            sort($productIds); // constant lock order avoids deadlocks between concurrent documents

            foreach ($productIds as $productId) {
                $target = $sign * (float) ($quantities[$productId] ?? 0);
                $delta = round($target - ($applied[$productId] ?? 0), 3);

                if (abs($delta) < self::EPSILON) {
                    continue;
                }

                $this->move($this->lockProduct((int) $productId), $delta, $reference, $reason);
            }
        });
    }

    public static function deliveryReference(Delivery $delivery): string
    {
        return 'DELIVERY:' . $delivery->id;
    }

    public static function receiptReference(DeliveryNote $note): string
    {
        return 'RECEIPT:' . $note->id;
    }

    private function move(Products $product, float $signedQuantity, string $reference, string $reason): void
    {
        $newQuantity = round((float) $product->stock_quantity + $signedQuantity, 3);

        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'item_quantity' => 'Stock insuffisant pour le produit "' . ($product->product_name ?? ('#' . $product->id))
                    . '". Stock disponible: ' . number_format((float) $product->stock_quantity, 3, '.', '')
                    . ', demande: ' . number_format(abs($signedQuantity), 3, '.', '') . '.',
            ]);
        }

        $product->stock_quantity = $newQuantity;
        $product->save();

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => Warehouse::query()->value('id'),
            'movement_type' => $signedQuantity > 0 ? 'in' : 'out',
            'quantity' => abs($signedQuantity),
            'reference_document' => $reference,
            'movement_date' => now(),
            'reason' => $reason,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        // Low-stock alerts only once the change is really committed.
        DB::afterCommit(fn () => event(new ProductStockChanged($product->fresh())));
    }

    private function lockProduct(int $productId): Products
    {
        return Products::withTrashed()->lockForUpdate()->findOrFail($productId);
    }
}

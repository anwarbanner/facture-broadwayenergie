<?php

namespace App\Observers;

use App\Events\ProductStockChanged;
use App\Models\DeliveryNoteItem;

class DeliveryNoteItemObserver
{
    public function created(DeliveryNoteItem $item): void
    {
        if (!$item->product_id) {
            return;
        }

        $qty = (float) ($item->received_qty ?: $item->quantity);
        $product = $item->product;
        $product->stock_quantity = (float) $product->stock_quantity + $qty;
        $product->save();

        event(new ProductStockChanged($product));
    }
}

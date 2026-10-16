<?php

namespace App\Observers;

use App\Events\ProductStockChanged;
use App\Models\DeliveryItem;

class DeliveryItemObserver
{
    public function created(DeliveryItem $item): void
    {
        if (!$item->product_id) {
            return;
        }

        $qty = (float) ($item->shipped_qty ?: $item->quantity);
        $product = $item->product;
        $product->stock_quantity = max(0, (float) $product->stock_quantity - $qty);
        $product->save();

        event(new ProductStockChanged($product));
    }
}

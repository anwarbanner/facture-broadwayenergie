<?php

namespace App\Services\Fulfillment;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\SalesQuote;
use App\Services\Numbering\DocumentNumberService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simple mode "Bon de livraison": one click on a quote prints a BL with the quote lines.
 *
 * The BL has no sales order and moves_stock = false: it is a printed document only, stock never moves
 * (owner decision 2026-10-08). It follows the quote: editing the quote rewrites the BL lines.
 */
class QuoteDeliveryService
{
    public function __construct(private DocumentNumberService $numbering)
    {
    }

    public function createFor(SalesQuote $quote): Delivery
    {
        return DB::transaction(function () use ($quote) {
            $quote = SalesQuote::lockForUpdate()->with('items')->findOrFail($quote->id);

            if ($existing = Delivery::where('sales_quote_id', $quote->id)->first()) {
                return $existing;
            }
            if ($quote->items->isEmpty()) {
                throw ValidationException::withMessages(['delivery' => 'Ce devis est vide : ajoutez au moins un article.']);
            }

            $delivery = Delivery::create([
                'delivery_number' => $this->numbering->next('deliveries', 'delivery_number', config('numbering.delivery', 'BLV')),
                'sales_order_id' => null,
                'sales_quote_id' => $quote->id,
                'client_id' => $quote->client_id,
                'delivery_date' => now()->toDateString(),
                'status' => 'delivered',
                'moves_stock' => false,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $this->copyLines($quote, $delivery);

            return $delivery;
        });
    }

    /** After the quote is edited: same client and lines on its BL. */
    public function syncFromQuote(SalesQuote $quote): void
    {
        $delivery = Delivery::where('sales_quote_id', $quote->id)->first();
        // A BL edited by hand ("Modifier le BL") keeps its own lines.
        if (!$delivery || !$delivery->follows_quote) {
            return;
        }

        $quote->load('items');
        $delivery->update(['client_id' => $quote->client_id, 'updated_by' => Auth::id()]);
        $delivery->items()->delete();
        $this->copyLines($quote, $delivery);
    }

    private function copyLines(SalesQuote $quote, Delivery $delivery): void
    {
        foreach ($quote->items as $item) {
            DeliveryItem::create([
                'delivery_id' => $delivery->id,
                'sales_order_item_id' => null,
                'product_id' => $item->product_id,
                'service_id' => $item->service_id,
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'shipped_qty' => $item->quantity,
            ]);
        }
    }
}

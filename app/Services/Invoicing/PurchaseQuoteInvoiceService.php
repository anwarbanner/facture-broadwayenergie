<?php

namespace App\Services\Invoicing;

use App\Models\PurchaseQuote;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Support\InvoiceLifecycle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simple mode "Faire la facture fournisseur": one click turns a supplier quote into a validated supplier
 * invoice with the same lines (no purchase order, no receipt: stock never moves). One invoice per supplier
 * quote (supplier_invoices.purchase_quote_id); it is then paid like any supplier invoice (PaymentService).
 */
class PurchaseQuoteInvoiceService
{
    public function __construct(private InvoiceLifecycleService $lifecycle)
    {
    }

    public function invoice(PurchaseQuote $quote): SupplierInvoice
    {
        return DB::transaction(function () use ($quote) {
            $quote = PurchaseQuote::lockForUpdate()->with('items')->findOrFail($quote->id);

            if (SupplierInvoice::where('purchase_quote_id', $quote->id)->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Ce devis fournisseur a deja sa facture.']);
            }
            if ($quote->order()->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Ce devis fournisseur a deja une commande : faites la facture depuis la commande.']);
            }
            if ($quote->status === 'rejected') {
                throw ValidationException::withMessages(['invoice' => 'Un devis fournisseur refuse ne peut pas etre facture.']);
            }
            if ($quote->items->isEmpty() || (float) $quote->total_ttc <= 0) {
                throw ValidationException::withMessages(['invoice' => 'Ce devis fournisseur est vide : ajoutez au moins un article.']);
            }

            $invoice = SupplierInvoice::create([
                'invoice_number' => InvoiceLifecycleService::provisionalNumber(),
                'document_type' => InvoiceLifecycle::TYPE_INVOICE,
                'state' => InvoiceLifecycle::STATE_DRAFT,
                'supplier_id' => $quote->supplier_id,
                'purchase_quote_id' => $quote->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'amount_ht' => $quote->total_ht,
                'tva_amount' => $quote->total_tva,
                'total_ttc' => $quote->total_ttc,
                'tva_rate' => $quote->tva_rate,
                'amount_paid' => 0,
                'balance_remaining' => $quote->total_ttc,
                'status' => 'unpaid',
                'currency' => $quote->currency,
                'exchange_rate' => 1,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($quote->items as $item) {
                SupplierInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'service_id' => $item->service_id,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'price_ht' => $item->price_ht,
                    'tva_rate' => $item->tva_rate,
                    'total_ht' => $item->total_ht,
                ]);
            }

            $this->lifecycle->validate($invoice);
            $quote->update(['status' => 'accepted', 'updated_by' => Auth::id()]);

            return $invoice->fresh();
        });
    }
}

<?php

namespace App\Services\Invoicing;

use App\Models\ClientInvoice;
use App\Models\ClientInvoiceItem;
use App\Models\Delivery;
use App\Models\SalesQuote;
use App\Support\InvoiceLifecycle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simple mode quote -> invoice: "Faire la facture" (validated, paid later) or "Encaisser" (one click:
 * invoice if needed + payment of what is left). One invoice per quote (client_invoices.sales_quote_id).
 *
 * No order and no delivery are created, so stock never moves (only deliveries/receipts move stock).
 * The invoice goes through the normal lifecycle (definitive number at validation) and the payment
 * through PaymentService, so accounting, statements and credit notes see it like any other invoice.
 */
class QuoteCashInService
{
    public function __construct(
        private InvoiceLifecycleService $lifecycle,
        private PaymentService $payments,
    ) {
    }

    /** "Faire la facture": validated invoice from the quote, not paid yet (the client pays later). */
    public function invoice(SalesQuote $quote): ClientInvoice
    {
        return DB::transaction(function () use ($quote) {
            $quote = SalesQuote::lockForUpdate()->with('items')->findOrFail($quote->id);
            if (ClientInvoice::where('sales_quote_id', $quote->id)->exists()) {
                throw ValidationException::withMessages(['cash_in' => 'Ce devis a deja sa facture.']);
            }

            return $this->createInvoice($quote, now()->addDays(30)->toDateString());
        });
    }

    /** "Encaisser": pays what is left on the quote's invoice, creating that invoice first when there is none. */
    public function cashIn(SalesQuote $quote, string $method): ClientInvoice
    {
        return DB::transaction(function () use ($quote, $method) {
            $quote = SalesQuote::lockForUpdate()->with('items')->findOrFail($quote->id);

            $invoice = ClientInvoice::where('sales_quote_id', $quote->id)->first()
                ?? $this->createInvoice($quote, now()->toDateString());
            if (!$invoice->isValidated() || round((float) $invoice->balance_remaining, 2) <= 0) {
                throw ValidationException::withMessages(['cash_in' => 'Ce devis est deja encaisse.']);
            }

            $this->payments->recordForInvoice($invoice, (float) $invoice->balance_remaining, [
                'payment_date' => now()->toDateString(),
                'method' => $method,
                'reference' => $quote->quote_number,
                'notes' => 'Encaissement du devis ' . $quote->quote_number,
            ]);

            return $invoice->fresh();
        });
    }

    /** Validated invoice with the quote lines; the quote is then "converted" and locked. */
    private function createInvoice(SalesQuote $quote, string $dueDate): ClientInvoice
    {
        if ($quote->order()->exists()) {
            throw ValidationException::withMessages(['cash_in' => 'Ce devis a deja une commande : encaissez la facture de la commande.']);
        }
        if ($quote->status === 'rejected') {
            throw ValidationException::withMessages(['cash_in' => 'Un devis refuse ne peut pas etre facture.']);
        }
        if ($quote->items->isEmpty() || (float) $quote->total_ttc <= 0) {
            throw ValidationException::withMessages(['cash_in' => 'Ce devis est vide : ajoutez au moins un article.']);
        }

        $invoice = ClientInvoice::create([
            'invoice_number' => InvoiceLifecycleService::provisionalNumber(),
            'document_type' => InvoiceLifecycle::TYPE_INVOICE,
            'state' => InvoiceLifecycle::STATE_DRAFT,
            'client_id' => $quote->client_id,
            'sales_quote_id' => $quote->id,
            // Its BL (simple mode), if any: the BL is then shown as invoiced and can no longer be changed.
            'delivery_id' => Delivery::where('sales_quote_id', $quote->id)->value('id'),
            'invoice_date' => now()->toDateString(),
            'due_date' => $dueDate,
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
            ClientInvoiceItem::create([
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
        $quote->update(['status' => 'converted', 'updated_by' => Auth::id()]);

        return $invoice->fresh();
    }
}

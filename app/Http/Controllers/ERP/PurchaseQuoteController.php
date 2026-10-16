<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Controllers\ERP\Concerns\FiltersDocuments;
use App\Http\Controllers\ERP\Concerns\HandlesDocumentLines;
use App\Models\Contact;
use App\Models\Products;
use App\Models\Service;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteItem;
use App\Services\Invoicing\PurchaseQuoteInvoiceService;
use App\Services\Numbering\DocumentNumberService;
use App\Services\Pdf\PurchaseQuotePdfService;
use App\Support\Branding;
use App\Support\DocumentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseQuoteController extends Controller
{
    use HandlesDocumentLines, FiltersDocuments, RedirectsToList;

    public function index()
    {
        $quotes = $this->filterDocuments(PurchaseQuote::query()->with(['supplier', 'order', 'invoice']), 'quote_number', 'supplier')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.purchases.quotes.index', compact('quotes'));
    }

    public function create()
    {
        $suppliers = Contact::suppliers()->where('status', 'active')->orderBy('company_name')->get();
        // "Faire un devis fournisseur" from the supplier list: the supplier is already chosen.
        $quote = new PurchaseQuote(['supplier_id' => $suppliers->firstWhere('id', (int) request('supplier_id'))?->id]);
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();

        return view('erp.purchases.quotes.create', compact('quote', 'suppliers', 'products', 'services'));
    }

    public function store(Request $request, DocumentNumberService $numbering)
    {
        $data = $request->validate(array_merge([
            'quote_number' => ['nullable', 'string', 'max:255'],
            'supplier_id' => DocumentRules::contact(),
            'date' => ['required', 'date'],
            'validity_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,sent,accepted,rejected'],
            'currency' => ['nullable', 'string', 'size:3'],
        ], DocumentRules::pricedLines()));

        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        $quote = DB::transaction(function () use ($numbering, $data, $totals, $items) {
            $quote = PurchaseQuote::create([
                'quote_number' => $numbering->next('purchase_quotes', 'quote_number', config('numbering.purchase_quote', 'DPA')),
                'supplier_id' => $this->contactId($data['supplier_id'], 'supplier'),
                'date' => $data['date'],
                'validity_date' => $data['validity_date'] ?? null,
                'status' => $data['status'],
                ...$this->documentAmounts($data, $totals),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                PurchaseQuoteItem::create([
                    'quote_id' => $quote->id,
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price_ht' => $item['price_ht'],
                    'tva_rate' => $item['tva_rate'],
                    'total_ht' => $item['total_ht'],
                ]);
            }

            return $quote;
        });

        // Simple mode: straight to the supplier quote, where "Imprimer" is.
        if (Branding::simpleMode()) {
            return redirect()->route('erp.purchase-quotes.show', $quote)->with('success', 'Devis fournisseur enregistre.');
        }

        return $this->createdRedirect('purchase-quotes', $quote, 'Demande d\'achat creee avec succes.');
    }

    /** "Imprimer": the PDF opens in the browser, ready to print. */
    public function printPdf(PurchaseQuote $purchase_quote, PurchaseQuotePdfService $pdfService)
    {
        $purchase_quote->load('items.service');
        return $pdfService->stream($purchase_quote);
    }

    public function show(PurchaseQuote $purchase_quote)
    {
        $purchase_quote->load(['supplier', 'items.product', 'items.service', 'order', 'invoice']);

        return view('erp.purchases.quotes.show', ['quote' => $purchase_quote]);
    }

    public function downloadPdf(PurchaseQuote $purchase_quote, PurchaseQuotePdfService $pdfService)
    {
        $purchase_quote->load('items.service');
        return $pdfService->download($purchase_quote);
    }

    /** "Faire la facture fournisseur": validated supplier invoice from this quote, paid later ("Payer"). */
    public function makeInvoice(PurchaseQuote $purchase_quote, PurchaseQuoteInvoiceService $invoices)
    {
        $invoice = $invoices->invoice($purchase_quote);

        return redirect()->route('erp.purchase-quotes.show', $purchase_quote)
            ->with('success', 'Facture fournisseur ' . $invoice->invoice_number . ' creee.');
    }

    public function edit(PurchaseQuote $purchase_quote)
    {
        if ($locked = $this->rejectIfInvoiced($purchase_quote)) {
            return $locked;
        }
        $suppliers = Contact::suppliers()->where('status', 'active')->orderBy('company_name')->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();
        $purchase_quote->load('items');

        return view('erp.purchases.quotes.edit', ['quote' => $purchase_quote, 'suppliers' => $suppliers, 'products' => $products, 'services' => $services]);
    }

    public function update(Request $request, PurchaseQuote $purchase_quote)
    {
        if ($locked = $this->rejectIfInvoiced($purchase_quote)) {
            return $locked;
        }
        $data = $request->validate(array_merge([
            'quote_number' => ['nullable', 'string', 'max:255'],
            'supplier_id' => DocumentRules::contact(),
            'date' => ['required', 'date'],
            'validity_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,sent,accepted,rejected'],
            'currency' => ['nullable', 'string', 'size:3'],
        ], DocumentRules::pricedLines()));

        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        DB::transaction(function () use ($purchase_quote, $data, $totals, $items) {
            $purchase_quote->update([
                'supplier_id' => $this->contactId($data['supplier_id'], 'supplier'),
                'date' => $data['date'],
                'validity_date' => $data['validity_date'] ?? null,
                'status' => $data['status'],
                ...$this->documentAmounts($data, $totals),
                'updated_by' => Auth::id(),
            ]);

            $purchase_quote->items()->delete();
            foreach ($items as $item) {
                PurchaseQuoteItem::create([
                    'quote_id' => $purchase_quote->id,
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price_ht' => $item['price_ht'],
                    'tva_rate' => $item['tva_rate'],
                    'total_ht' => $item['total_ht'],
                ]);
            }
        });

        return redirect()->route('erp.purchase-quotes.show', $purchase_quote)->with('success', 'Demande d\'achat mise a jour avec succes.');
    }

    public function destroy(PurchaseQuote $purchase_quote)
    {
        if ($locked = $this->rejectIfInvoiced($purchase_quote)) {
            return $locked;
        }
        $purchase_quote->delete();

        return redirect()->route('erp.purchase-quotes.index')->with('success', 'Demande d\'achat supprimee avec succes.');
    }

    public function convertToOrder(PurchaseQuote $purchase_quote, DocumentNumberService $numbering)
    {
        if (in_array($purchase_quote->status, ['rejected'], true) || $purchase_quote->invoice()->exists()) {
            return back()->withErrors(['status' => 'Cette demande d\'achat ne peut pas etre convertie.']);
        }

        $existingOrder = PurchaseOrder::where('quote_id', $purchase_quote->id)->latest()->first();
        if ($existingOrder) {
            return redirect()->route('erp.purchase-orders.edit', $existingOrder)->with('success', 'Une commande d\'achat existe deja pour cette demande.');
        }

        $purchase_quote->load('items');

        $order = DB::transaction(function () use ($numbering, $purchase_quote) {
            $order = PurchaseOrder::create([
                'order_number' => $numbering->next('purchase_orders', 'order_number', config('numbering.purchase_order', 'BCA')),
                'supplier_id' => $purchase_quote->supplier_id,
                'quote_id' => $purchase_quote->id,
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $purchase_quote->validity_date,
                'status' => 'confirmed',
                'total_ht' => $purchase_quote->total_ht,
                'total_tva' => $purchase_quote->total_tva,
                'total_ttc' => $purchase_quote->total_ttc,
                'tva_rate' => $purchase_quote->tva_rate,
                'currency' => $purchase_quote->currency,
                'exchange_rate' => 1,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($purchase_quote->items as $item) {
                PurchaseOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'service_id' => $item->service_id,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'price_ht' => $item->price_ht,
                    'tva_rate' => $item->tva_rate,
                    'total_ht' => $item->total_ht,
                    'delivered_qty' => 0,
                ]);
            }

            return $order;
        });

        return redirect()->route('erp.purchase-orders.edit', $order)->with('success', 'Commande d\'achat creee depuis la demande.');
    }

    /** An invoiced supplier quote matches its supplier invoice: it can no longer change. */
    private function rejectIfInvoiced(PurchaseQuote $quote)
    {
        if (!$quote->invoice()->exists()) {
            return null;
        }

        return redirect()->route('erp.purchase-quotes.show', $quote)
            ->with('error', 'Ce devis fournisseur est deja facture : il ne peut plus etre modifie ni supprime.');
    }
}

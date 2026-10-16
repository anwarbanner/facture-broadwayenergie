<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Controllers\ERP\Concerns\FiltersDocuments;
use App\Http\Controllers\ERP\Concerns\HandlesDocumentLines;
use App\Http\Requests\UpsertSalesQuoteRequest;
use App\Models\Contact;
use App\Models\Products;
use App\Models\Service;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesQuote;
use App\Models\SalesQuoteItem;
use App\Services\Fulfillment\QuoteDeliveryService;
use App\Services\Invoicing\QuoteCashInService;
use App\Services\Numbering\DocumentNumberService;
use App\Services\Pdf\SalesQuotePdfService;
use App\Support\Branding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesQuoteController extends Controller
{
    use HandlesDocumentLines, FiltersDocuments, RedirectsToList;

    public function index()
    {
        $quotes = $this->filterDocuments(SalesQuote::query()->with(['client', 'order', 'invoice', 'delivery']), 'quote_number', 'client')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.sales_quotes.index', compact('quotes'));
    }

    public function create()
    {
        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();
        // "Faire un devis" from the client list: the client is already chosen.
        $quote = new SalesQuote(['client_id' => $clients->firstWhere('id', (int) request('client_id'))?->id]);

        return view('erp.sales_quotes.create', compact('clients', 'products', 'services', 'quote'));
    }

    public function store(UpsertSalesQuoteRequest $request, DocumentNumberService $numbering)
    {
        $data = $request->validated();
        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        $quote = DB::transaction(function () use ($numbering, $data, $totals, $items) {
            $quote = SalesQuote::create([
                'quote_number' => $numbering->next('sales_quotes', 'quote_number', config('numbering.sales_quote', 'DEV')),
                'client_id' => $this->contactId($data['client_id'], 'client'),
                'date' => $data['date'],
                'validity_date' => $data['validity_date'] ?? null,
                'status' => $data['status'],
                ...$this->documentAmounts($data, $totals),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                SalesQuoteItem::create([
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

        // Simple mode: straight to the quote, where "Imprimer" and "Encaisser" are.
        if (Branding::simpleMode()) {
            return redirect()->route('erp.sales-quotes.show', $quote)->with('success', 'Devis enregistre. Vous pouvez l\'imprimer.');
        }

        return $this->createdRedirect('sales-quotes', $quote, 'Devis client cree.');
    }

    public function show(SalesQuote $sales_quote)
    {
        $sales_quote->load(['client', 'items.product', 'order', 'invoice', 'delivery']);
        $sales_quote->load('items.service');

        return view('erp.sales_quotes.show', ['quote' => $sales_quote]);
    }

    public function downloadPdf(SalesQuote $sales_quote, SalesQuotePdfService $pdfService)
    {
        $sales_quote->load('items.service');
        return $pdfService->download($sales_quote);
    }

    /** "Imprimer": the PDF opens in the browser, ready to print. */
    public function printPdf(SalesQuote $sales_quote, SalesQuotePdfService $pdfService)
    {
        $sales_quote->load('items.service');
        return $pdfService->stream($sales_quote);
    }

    /** "Encaisser" (one click): validated invoice + full payment, no order nor delivery (stock unchanged). */
    public function cashIn(Request $request, SalesQuote $sales_quote, QuoteCashInService $cashIn)
    {
        $data = $request->validate(['method' => ['required', 'in:cash,check,transfer']]);
        $invoice = $cashIn->cashIn($sales_quote, $data['method']);

        return redirect()->route('erp.sales-quotes.show', $sales_quote)
            ->with('success', 'Encaisse : facture ' . $invoice->invoice_number . ' payee.');
    }

    /** "Bon de livraison" (one click): BL with the quote lines, printed only (stock never moves). */
    public function makeDelivery(SalesQuote $sales_quote, QuoteDeliveryService $deliveries)
    {
        $existed = $sales_quote->delivery()->exists();
        $delivery = $deliveries->createFor($sales_quote);

        // From the BL list ("Faire un bon de livraison"): open the new BL, ready to print.
        if (request('open') === 'delivery') {
            return redirect()->route('erp.deliveries.show', $delivery)
                ->with('success', 'Bon de livraison ' . $delivery->delivery_number . ' cree. Vous pouvez l\'imprimer.');
        }

        return redirect()->route('erp.sales-quotes.show', $sales_quote)
            ->with('success', $existed ? 'Ce devis a deja son bon de livraison ' . $delivery->delivery_number . '.'
                : 'Bon de livraison ' . $delivery->delivery_number . ' cree. Vous pouvez l\'imprimer.');
    }

    /** "Faire la facture": validated invoice from the quote, paid later ("Encaisser"). */
    public function makeInvoice(SalesQuote $sales_quote, QuoteCashInService $cashIn)
    {
        $invoice = $cashIn->invoice($sales_quote);

        return redirect()->route('erp.sales-quotes.show', $sales_quote)
            ->with('success', 'Facture ' . $invoice->invoice_number . ' creee. Vous pouvez l\'imprimer, et l\'encaisser quand le client paie.');
    }

    public function edit(SalesQuote $sales_quote)
    {
        if ($locked = $this->rejectIfCashedIn($sales_quote)) {
            return $locked;
        }
        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();
        $sales_quote->load('items');

        return view('erp.sales_quotes.edit', ['quote' => $sales_quote, 'clients' => $clients, 'products' => $products, 'services' => $services]);
    }

    public function update(UpsertSalesQuoteRequest $request, SalesQuote $sales_quote, QuoteDeliveryService $deliveries)
    {
        if ($locked = $this->rejectIfCashedIn($sales_quote)) {
            return $locked;
        }
        $data = $request->validated();
        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        DB::transaction(function () use ($sales_quote, $data, $totals, $items, $deliveries) {
            $sales_quote->update([
                'client_id' => $this->contactId($data['client_id'], 'client'),
                'date' => $data['date'],
                'validity_date' => $data['validity_date'] ?? null,
                'status' => $data['status'],
                ...$this->documentAmounts($data, $totals),
                'updated_by' => Auth::id(),
            ]);

            $sales_quote->items()->delete();
            foreach ($items as $item) {
                SalesQuoteItem::create([
                    'quote_id' => $sales_quote->id,
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price_ht' => $item['price_ht'],
                    'tva_rate' => $item['tva_rate'],
                    'total_ht' => $item['total_ht'],
                ]);
            }

            // Its bon de livraison (simple mode) follows the quote.
            $deliveries->syncFromQuote($sales_quote);
        });

        return redirect()->route('erp.sales-quotes.show', $sales_quote)->with('success', 'Devis client mis a jour.');
    }

    public function destroy(SalesQuote $sales_quote)
    {
        if ($locked = $this->rejectIfCashedIn($sales_quote)) {
            return $locked;
        }
        DB::transaction(function () use ($sales_quote) {
            // Its BL goes with it, unless the BL was edited by hand: then it stays, as a BL without quote.
            if ($bl = $sales_quote->delivery) {
                $bl->follows_quote ? $bl->delete() : $bl->update(['sales_quote_id' => null]);
            }
            $sales_quote->delete();
        });

        return redirect()->route('erp.sales-quotes.index')->with('success', 'Devis client supprime.');
    }

    public function convertToOrder(SalesQuote $sales_quote, DocumentNumberService $numbering)
    {
        if (in_array($sales_quote->status, ['rejected', 'converted'], true) || $sales_quote->invoice()->exists()) {
            return back()->withErrors(['status' => 'Ce devis ne peut pas etre converti.']);
        }

        $existingOrder = SalesOrder::where('quote_id', $sales_quote->id)->latest()->first();
        if ($existingOrder) {
            return redirect()->route('erp.sales-orders.edit', $existingOrder)->with('success', 'Une commande existe deja pour ce devis.');
        }

        $sales_quote->load('items');

        $order = DB::transaction(function () use ($numbering, $sales_quote) {
            $order = SalesOrder::create([
                'order_number' => $numbering->next('sales_orders', 'order_number', config('numbering.sales_order', 'CMD')),
                'client_id' => $sales_quote->client_id,
                'quote_id' => $sales_quote->id,
                'order_date' => now()->toDateString(),
                'delivery_date' => $sales_quote->validity_date,
                'status' => 'confirmed',
                'total_ht' => $sales_quote->total_ht,
                'total_tva' => $sales_quote->total_tva,
                'total_ttc' => $sales_quote->total_ttc,
                'tva_rate' => $sales_quote->tva_rate,
                'currency' => $sales_quote->currency,
                'exchange_rate' => 1,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($sales_quote->items as $item) {
                SalesOrderItem::create([
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

            $sales_quote->update(['status' => 'converted', 'updated_by' => Auth::id()]);

            return $order;
        });

        return redirect()->route('erp.sales-orders.edit', $order)->with('success', 'Commande client creee depuis le devis.');
    }

    /** A cashed-in quote matches its paid invoice: it can no longer change. */
    private function rejectIfCashedIn(SalesQuote $quote)
    {
        if (!$quote->invoice()->exists()) {
            return null;
        }

        return redirect()->route('erp.sales-quotes.show', $quote)
            ->with('error', 'Ce devis est deja encaisse : il ne peut plus etre modifie ni supprime.');
    }
}

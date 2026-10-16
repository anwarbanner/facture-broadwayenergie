<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Controllers\ERP\Concerns\FiltersDocuments;
use App\Http\Controllers\ERP\Concerns\HandlesDocumentLines;
use App\Http\Requests\UpsertSalesOrderRequest;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Products;
use App\Models\Service;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesQuote;
use App\Services\Fulfillment\OrderFulfillmentService;
use App\Services\Numbering\DocumentNumberService;
use App\Services\Pdf\SalesOrderPdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    use HandlesDocumentLines, FiltersDocuments, RedirectsToList;

    public function index()
    {
        $orders = $this->filterDocuments(SalesOrder::query()->with(['client', 'quote', 'delivery']), 'order_number', 'client')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.sales_orders.index', compact('orders'));
    }

    public function create()
    {
        $order = new SalesOrder();
        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();
        $quotes = SalesQuote::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();

        return view('erp.sales_orders.create', compact('order', 'clients', 'quotes', 'products', 'services'));
    }

    public function store(UpsertSalesOrderRequest $request, DocumentNumberService $numbering)
    {
        $data = $request->validated();
        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        $order = DB::transaction(function () use ($numbering, $data, $totals, $items) {
            $order = SalesOrder::create([
                'order_number' => $numbering->next('sales_orders', 'order_number', config('numbering.sales_order', 'CMD')),
                'client_id' => $this->contactId($data['client_id'], 'client'),
                'quote_id' => $data['quote_id'] ?? null,
                'order_date' => $data['order_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'status' => $data['status'],
                ...$this->documentAmounts($data, $totals),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                SalesOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price_ht' => $item['price_ht'],
                    'tva_rate' => $item['tva_rate'],
                    'total_ht' => $item['total_ht'],
                ]);
            }

            return $order;
        });

        return $this->createdRedirect('sales-orders', $order, 'Commande client creee.');
    }

    public function show(SalesOrder $sales_order)
    {
        $sales_order->load(['client', 'quote', 'items.product', 'items.service', 'deliveries', 'delivery']);

        return view('erp.sales_orders.show', ['order' => $sales_order]);
    }

    public function downloadPdf(SalesOrder $sales_order, SalesOrderPdfService $pdfService)
    {
        $sales_order->load('items.service');
        return $pdfService->download($sales_order);
    }

    public function edit(SalesOrder $sales_order)
    {
        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();
        $quotes = SalesQuote::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();
        $sales_order->load('items');

        return view('erp.sales_orders.edit', ['order' => $sales_order, 'clients' => $clients, 'quotes' => $quotes, 'products' => $products, 'services' => $services]);
    }

    public function update(UpsertSalesOrderRequest $request, SalesOrder $sales_order)
    {
        if ($sales_order->deliveries()->exists()) {
            return redirect()->route('erp.sales-orders.show', $sales_order)->withErrors(['status' => 'Cette commande a deja des livraisons : elle ne peut plus etre modifiee ni supprimee.']);
        }

        $data = $request->validated();
        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        DB::transaction(function () use ($sales_order, $data, $totals, $items) {
            $sales_order->update([
                'client_id' => $this->contactId($data['client_id'], 'client'),
                'quote_id' => $data['quote_id'] ?? null,
                'order_date' => $data['order_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'status' => $data['status'],
                ...$this->documentAmounts($data, $totals),
                'updated_by' => Auth::id(),
            ]);

            $sales_order->items()->delete();
            foreach ($items as $item) {
                SalesOrderItem::create([
                    'order_id' => $sales_order->id,
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

        return redirect()->route('erp.sales-orders.show', $sales_order)->with('success', 'Commande client mise a jour.');
    }

    public function destroy(SalesOrder $sales_order)
    {
        if ($sales_order->deliveries()->exists()) {
            return redirect()->route('erp.sales-orders.show', $sales_order)->withErrors(['status' => 'Cette commande a deja des livraisons : elle ne peut plus etre modifiee ni supprimee.']);
        }

        $sales_order->delete();

        return redirect()->route('erp.sales-orders.index')->with('success', 'Commande client supprimee.');
    }

    /** Creates a delivery for what is left to deliver (partial deliveries allowed). */
    public function convertToDelivery(SalesOrder $sales_order, DocumentNumberService $numbering, OrderFulfillmentService $fulfillment)
    {
        if (!in_array($sales_order->status, ['draft', 'confirmed', 'partial'], true)) {
            return back()->withErrors(['status' => 'Seules les commandes brouillon, confirmees ou partielles peuvent etre converties en livraison.']);
        }

        $remaining = $fulfillment->remainingToDeliver($sales_order);
        if ($remaining->isEmpty()) {
            return back()->withErrors(['status' => 'Toutes les quantites de cette commande sont deja livrees ou planifiees dans une livraison.']);
        }

        $delivery = DB::transaction(function () use ($numbering, $sales_order, $remaining, $fulfillment) {
            $delivery = Delivery::create([
                'delivery_number' => $numbering->next('deliveries', 'delivery_number', config('numbering.delivery', 'BLV')),
                'sales_order_id' => $sales_order->id,
                'client_id' => $sales_order->client_id,
                'delivery_date' => now()->toDateString(),
                'shipping_address' => null,
                'status' => 'pending',
                'tracking_number' => null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($remaining as $line) {
                DeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'sales_order_item_id' => $line['item']->id,
                    'product_id' => $line['item']->product_id,
                    'service_id' => $line['item']->service_id,
                    'item_name' => $line['item']->item_name,
                    'quantity' => $line['remaining'],
                    'shipped_qty' => $line['remaining'],
                ]);
            }

            $fulfillment->syncSalesOrder($sales_order);

            return $delivery;
        });

        return redirect()->route('erp.deliveries.edit', $delivery)->with('success', 'Livraison creee pour le reste a livrer de la commande.');
    }
}

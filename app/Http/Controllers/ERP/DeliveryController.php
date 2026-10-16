<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Controllers\ERP\Concerns\FiltersDocuments;
use App\Http\Controllers\ERP\Concerns\HandlesDocumentLines;
use App\Http\Requests\UpsertDeliveryRequest;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\ClientInvoice;
use App\Models\ClientInvoiceItem;
use App\Models\Products;
use App\Models\Service;
use App\Models\SalesOrder;
use App\Services\Fulfillment\OrderFulfillmentService;
use App\Services\Numbering\DocumentNumberService;
use App\Services\Pdf\DeliveryPdfService;
use App\Services\Stock\StockService;
use App\Services\Invoicing\InvoiceLifecycleService;
use App\Support\FinancialHelper;
use App\Support\InvoiceLifecycle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    use HandlesDocumentLines, FiltersDocuments, RedirectsToList;

    public function __construct(private StockService $stock, private OrderFulfillmentService $fulfillment)
    {
    }

    public function index()
    {
        $deliveries = $this->filterDocuments(Delivery::query()->with(['client', 'order', 'quote', 'clientInvoice']), 'delivery_number', 'client')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.deliveries.index', compact('deliveries'));
    }

    public function create()
    {
        $delivery = new Delivery();
        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();
        $orders = SalesOrder::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();

        return view('erp.deliveries.create', compact('delivery', 'clients', 'orders', 'products', 'services'));
    }

    public function store(UpsertDeliveryRequest $request, DocumentNumberService $numbering)
    {
        $data = $request->validated();
        $items = $this->normalizeItems($data, SalesOrder::find($data['sales_order_id']));
        if (empty($items)) {
            return back()->withErrors(['item_name' => 'Ajoutez au moins un article (produit, service ou article manuel).'])->withInput();
        }

        $delivery = DB::transaction(function () use ($data, $items, $numbering) {
            $delivery = Delivery::create([
                'delivery_number' => $numbering->next('deliveries', 'delivery_number', config('numbering.delivery', 'BLV')),
                'sales_order_id' => $data['sales_order_id'],
                'client_id' => $data['client_id'],
                'delivery_date' => $data['delivery_date'],
                'shipping_address' => $data['shipping_address'] ?? null,
                'status' => $data['status'],
                'tracking_number' => $data['tracking_number'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                DeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'sales_order_item_id' => $item['sales_order_item_id'],
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'shipped_qty' => $item['shipped_qty'],
                ]);
            }

            $this->stock->syncDelivery($delivery);
            $this->fulfillment->syncSalesOrder($delivery->order()->first());

            return $delivery;
        });

        return $this->createdRedirect('deliveries', $delivery, 'Livraison creee.');
    }

    public function show(Delivery $delivery)
    {
        $delivery->load(['client', 'order', 'quote', 'items.product', 'items.service', 'clientInvoice']);

        return view('erp.deliveries.show', compact('delivery'));
    }

    public function downloadPdf(Delivery $delivery, DeliveryPdfService $pdfService)
    {
        $delivery->load('items.service');
        return $pdfService->download($delivery);
    }

    /** Simple mode "Faire un bon de livraison" from the BL list: pick the quote, the BL is made in one click. */
    public function chooseQuote()
    {
        $quotes = $this->filterDocuments(
            \App\Models\SalesQuote::query()->with('client')
                ->whereDoesntHave('delivery')->whereDoesntHave('order')->where('status', '!=', 'rejected')->has('items'),
            'quote_number',
            'client'
        )->latest()->paginate(20)->withQueryString();

        return view('erp.deliveries.choose_quote', compact('quotes'));
    }

    /** "Imprimer": the PDF opens in the browser, ready to print. */
    public function printPdf(Delivery $delivery, DeliveryPdfService $pdfService)
    {
        $delivery->load('items.service');
        return $pdfService->stream($delivery);
    }

    public function edit(Delivery $delivery)
    {
        // A BL without order (simple mode) has its own simple form.
        if ($delivery->isSimple()) {
            return redirect()->route('erp.deliveries.simple-edit', $delivery);
        }
        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();
        $orders = SalesOrder::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();
        $delivery->load('items');

        return view('erp.deliveries.edit', compact('delivery', 'clients', 'orders', 'products', 'services'));
    }

    public function update(UpsertDeliveryRequest $request, Delivery $delivery)
    {
        if ($delivery->isSimple()) {
            return redirect()->route('erp.deliveries.show', $delivery)
                ->with('error', 'Ce bon de livraison se modifie avec le bouton « Modifier le BL ».');
        }
        // Once invoiced, the bon de livraison is the basis of the invoice: it must not change any more.
        if (ClientInvoice::issuedOrDraftFor('delivery_id', $delivery->id)->exists()) {
            return redirect()->route('erp.deliveries.show', $delivery)->withErrors(['status' => 'Ce bon de livraison est deja facture : il ne peut plus etre modifie ni supprime.']);
        }

        $data = $request->validated();
        $items = $this->normalizeItems($data, SalesOrder::find($data['sales_order_id']));
        if (empty($items)) {
            return back()->withErrors(['item_name' => 'Ajoutez au moins un article (produit, service ou article manuel).'])->withInput();
        }

        DB::transaction(function () use ($delivery, $data, $items) {
            $previousOrderId = $delivery->sales_order_id;
            $delivery->update([
                'sales_order_id' => $data['sales_order_id'],
                'client_id' => $data['client_id'],
                'delivery_date' => $data['delivery_date'],
                'shipping_address' => $data['shipping_address'] ?? null,
                'status' => $data['status'],
                'tracking_number' => $data['tracking_number'] ?? null,
                'updated_by' => Auth::id(),
            ]);

            $delivery->items()->delete();
            foreach ($items as $item) {
                DeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'sales_order_item_id' => $item['sales_order_item_id'],
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'shipped_qty' => $item['shipped_qty'],
                ]);
            }

            // Only the difference with what was already moved for this delivery is applied.
            $this->stock->syncDelivery($delivery);
            $this->fulfillment->syncSalesOrder($delivery->order()->first());
            if ((int) $previousOrderId !== (int) $delivery->sales_order_id) {
                $this->fulfillment->syncSalesOrder(SalesOrder::find($previousOrderId));
            }
        });

        return redirect()->route('erp.deliveries.show', $delivery)->with('success', 'Livraison mise a jour.');
    }

    public function destroy(Delivery $delivery)
    {
        // Once invoiced, the bon de livraison is the basis of the invoice: it must not change any more.
        if (ClientInvoice::issuedOrDraftFor('delivery_id', $delivery->id)->exists()) {
            return redirect()->route('erp.deliveries.show', $delivery)->withErrors(['status' => 'Ce bon de livraison est deja facture : il ne peut plus etre modifie ni supprime.']);
        }

        DB::transaction(function () use ($delivery) {
            $this->stock->syncDelivery($delivery, deleting: true);
            $delivery->delete();
            $this->fulfillment->syncSalesOrder($delivery->order()->first());
        });

        return redirect()->route('erp.deliveries.index')->with('success', 'Livraison supprimee.');
    }

    public function convertToInvoice(Delivery $delivery, DocumentNumberService $numbering)
    {
        // A simple BL has no prices: it is invoiced by cashing in its quote (one invoice per quote).
        if ($delivery->isSimple()) {
            return back()->withErrors(['status' => $delivery->isFromQuote()
                ? 'Ce bon de livraison vient d\'un devis : encaissez le devis pour faire la facture.'
                : 'Ce bon de livraison n\'a pas de prix : faites un devis puis encaissez-le pour faire la facture.']);
        }

        if (!in_array($delivery->status, ['pending', 'shipped', 'delivered'], true)) {
            return back()->withErrors(['status' => 'Cette livraison ne peut pas etre convertie en facture.']);
        }

        $existingInvoice = ClientInvoice::issuedOrDraftFor('delivery_id', $delivery->id)->latest()->first();
        if ($existingInvoice) {
            return redirect()->route('erp.client-invoices.edit', $existingInvoice)->with('success', 'Une facture existe deja pour cette livraison.');
        }

        $delivery->load(['order.items', 'items.salesOrderItem']);

        // One VAT rate per document: the order's, applied to the invoice total HT.
        $tvaRate = (float) ($delivery->order?->tva_rate ?? \App\Support\Branding::defaultVatRate());
        $totalHt = 0.0;
        $preparedItems = [];

        foreach ($delivery->items as $item) {
            // Bill what was really shipped: a line shipped at 0 is not billed.
            $qty = (float) $item->shipped_qty;
            if ($qty <= 0) {
                continue;
            }

            // Price of the exact order line; legacy lines without link fall back to product/service matching.
            $orderItem = $item->salesOrderItem;
            if (!$orderItem && $item->product_id) {
                $orderItem = $delivery->order?->items?->firstWhere('product_id', $item->product_id);
            }
            if (!$orderItem && $item->service_id) {
                $orderItem = $delivery->order?->items?->firstWhere('service_id', $item->service_id);
            }
            if (!$orderItem && $item->item_name) {
                $orderItem = $delivery->order?->items?->firstWhere('item_name', $item->item_name);
            }
            $priceHt = (float) ($orderItem->price_ht ?? 0);
            $lineHt = round($qty * $priceHt, 2);

            $preparedItems[] = [
                'product_id' => $item->product_id,
                'service_id' => $item->service_id,
                'item_name' => $item->item_name,
                'quantity' => $qty,
                'price_ht' => $priceHt,
                'tva_rate' => $tvaRate,
                'total_ht' => $lineHt,
                'delivery_item_id' => $item->id,
            ];

            $totalHt += $lineHt;
        }

        if (empty($preparedItems)) {
            return back()->withErrors(['items' => 'Aucune ligne livree valide a convertir en facture.']);
        }

        ['total_ht' => $totalHt, 'total_tva' => $totalTva, 'total_ttc' => $totalTtc] = FinancialHelper::documentTotals($totalHt, $tvaRate);
        $dueDate = now()->addDays(30)->toDateString();
        $amountPaid = FinancialHelper::paidAmount($totalTtc, 0);
        $balance = FinancialHelper::balance($totalTtc, $amountPaid);
        $status = FinancialHelper::invoiceStatus($totalTtc, $amountPaid, $dueDate);

        $invoice = DB::transaction(function () use ($delivery, $numbering, $tvaRate, $totalHt, $totalTva, $totalTtc, $dueDate, $amountPaid, $balance, $status, $preparedItems) {
            $invoice = ClientInvoice::create([
                'invoice_number' => InvoiceLifecycleService::provisionalNumber(),
                'document_type' => InvoiceLifecycle::TYPE_INVOICE,
                'state' => InvoiceLifecycle::STATE_DRAFT,
                'client_id' => $delivery->client_id,
                'sales_order_id' => $delivery->sales_order_id,
                'delivery_id' => $delivery->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'amount_ht' => round($totalHt, 2),
                'tva_amount' => round($totalTva, 2),
                'total_ttc' => $totalTtc,
                'tva_rate' => $tvaRate,
                'amount_paid' => $amountPaid,
                'balance_remaining' => $balance,
                'status' => $status,
                'payment_terms' => '30 days',
                'currency' => $delivery->order->currency ?? \App\Support\Branding::defaultCurrency(),
                'exchange_rate' => 1,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($preparedItems as $item) {
                ClientInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'delivery_item_id' => $item['delivery_item_id'],
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price_ht' => $item['price_ht'],
                    'tva_rate' => $item['tva_rate'],
                    'total_ht' => $item['total_ht'],
                ]);
            }

            $delivery->update(['status' => 'delivered', 'updated_by' => Auth::id()]);
            $this->stock->syncDelivery($delivery);
            $this->fulfillment->syncSalesOrder($delivery->order()->first());

            return $invoice;
        });

        return redirect()->route('erp.client-invoices.edit', $invoice)->with('success', 'Facture client creee depuis la livraison.');
    }

    private function normalizeItems(array $data, ?SalesOrder $order = null): array
    {
        $items = [];
        $usedSources = [];

        $sourceIds = $data['item_source_id'] ?? [];
        $modes = $data['item_mode'] ?? [];
        $productIds = $data['item_product_id'] ?? [];
        $serviceIds = $data['item_service_id'] ?? [];
        $names = $data['item_name'] ?? [];
        $quantities = $data['item_quantity'] ?? [];
        $shipped = $data['item_shipped_qty'] ?? [];

        $rowCount = max(count($modes), count($productIds), count($serviceIds), count($names), count($quantities), count($shipped));

        for ($i = 0; $i < $rowCount; $i++) {
            $productId = !empty($productIds[$i]) ? (int) $productIds[$i] : null;
            $serviceId = !empty($serviceIds[$i]) ? (int) $serviceIds[$i] : null;
            $name = trim((string) ($names[$i] ?? ''));
            $mode = $modes[$i] ?? ($productId ? 'product' : ($serviceId ? 'service' : 'manual'));
            [$productId, $serviceId, $name] = match ($mode) {
                'product' => [$productId, null, ''],
                'service' => [null, $serviceId, ''],
                default => [null, null, $name],
            };
            $qty = (float) ($quantities[$i] ?? 0);
            $shippedQty = (float) ($shipped[$i] ?? $qty);

            if ((!$productId && !$serviceId && $name === '') || $qty <= 0) {
                continue;
            }

            if ($shippedQty <= 0) {
                $shippedQty = $qty;
            }

            $sourceId = $this->fulfillment->salesSourceFor(
                $order,
                !empty($sourceIds[$i]) ? (int) $sourceIds[$i] : null,
                $productId,
                $serviceId,
                $usedSources,
                $name !== '' ? $name : null
            );
            if ($sourceId) {
                $usedSources[] = $sourceId;
            }

            $items[] = [
                'sales_order_item_id' => $sourceId,
                'product_id' => $productId,
                'service_id' => $serviceId,
                'item_name' => $name !== '' ? $name : null,
                'quantity' => $qty,
                'shipped_qty' => $shippedQty,
            ];
        }

        return $items;
    }
}

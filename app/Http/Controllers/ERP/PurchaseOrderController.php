<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Controllers\ERP\Concerns\FiltersDocuments;
use App\Http\Controllers\ERP\Concerns\HandlesDocumentLines;
use App\Models\Contact;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Products;
use App\Models\Service;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseQuote;
use App\Services\Fulfillment\OrderFulfillmentService;
use App\Services\Numbering\DocumentNumberService;
use App\Services\Pdf\PurchaseOrderPdfService;
use App\Support\DocumentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    use HandlesDocumentLines, FiltersDocuments, RedirectsToList;

    public function index()
    {
        $orders = $this->filterDocuments(PurchaseOrder::query()->with(['supplier', 'quote', 'deliveryNote']), 'order_number', 'supplier')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.purchases.orders.index', compact('orders'));
    }

    public function create()
    {
        $order = new PurchaseOrder();
        $suppliers = Contact::suppliers()->where('status', 'active')->orderBy('company_name')->get();
        $quotes = PurchaseQuote::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();

        return view('erp.purchases.orders.create', compact('order', 'suppliers', 'quotes', 'products', 'services'));
    }

    public function store(Request $request, DocumentNumberService $numbering)
    {
        $data = $request->validate(array_merge([
            'order_number' => ['nullable', 'string', 'max:255'],
            'supplier_id' => DocumentRules::contact(),
            'quote_id' => ['nullable', 'integer', 'exists:purchase_quotes,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,confirmed,partial,delivered,cancelled'],
            'currency' => ['nullable', 'string', 'size:3'],
        ], DocumentRules::pricedLines()));

        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        $order = DB::transaction(function () use ($numbering, $data, $totals, $items) {
            $order = PurchaseOrder::create([
                'order_number' => $numbering->next('purchase_orders', 'order_number', config('numbering.purchase_order', 'BCA')),
                'supplier_id' => $this->contactId($data['supplier_id'], 'supplier'),
                'quote_id' => $data['quote_id'] ?? null,
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => $data['status'],
                ...$this->documentAmounts($data, $totals),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price_ht' => $item['price_ht'],
                    'tva_rate' => $item['tva_rate'],
                    'total_ht' => $item['total_ht'],
                    'delivered_qty' => 0,
                ]);
            }

            return $order;
        });

        return $this->createdRedirect('purchase-orders', $order, 'Commande d\'achat creee avec succes.');
    }

    public function show(PurchaseOrder $purchase_order)
    {
        $purchase_order->load(['supplier', 'quote', 'items.product', 'items.service', 'deliveryNotes', 'deliveryNote']);

        return view('erp.purchases.orders.show', ['order' => $purchase_order]);
    }

    public function downloadPdf(PurchaseOrder $purchase_order, PurchaseOrderPdfService $pdfService)
    {
        $purchase_order->load('items.service');
        return $pdfService->download($purchase_order);
    }

    public function edit(PurchaseOrder $purchase_order)
    {
        $suppliers = Contact::suppliers()->where('status', 'active')->orderBy('company_name')->get();
        $quotes = PurchaseQuote::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();
        $purchase_order->load('items');

        return view('erp.purchases.orders.edit', ['order' => $purchase_order, 'suppliers' => $suppliers, 'quotes' => $quotes, 'products' => $products, 'services' => $services]);
    }

    public function update(Request $request, PurchaseOrder $purchase_order)
    {
        if ($purchase_order->deliveryNotes()->exists()) {
            return redirect()->route('erp.purchase-orders.show', $purchase_order)->withErrors(['status' => 'Cette commande a deja des receptions : elle ne peut plus etre modifiee ni supprimee.']);
        }

        $data = $request->validate(array_merge([
            'supplier_id' => DocumentRules::contact(),
            'quote_id' => ['nullable', 'integer', 'exists:purchase_quotes,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,confirmed,partial,delivered,cancelled'],
            'currency' => ['nullable', 'string', 'size:3'],
        ], DocumentRules::pricedLines()));

        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        DB::transaction(function () use ($purchase_order, $data, $totals, $items) {
            $purchase_order->update([
                'supplier_id' => $this->contactId($data['supplier_id'], 'supplier'),
                'quote_id' => $data['quote_id'] ?? null,
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => $data['status'],
                ...$this->documentAmounts($data, $totals),
                'updated_by' => Auth::id(),
            ]);

            $purchase_order->items()->delete();
            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'order_id' => $purchase_order->id,
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price_ht' => $item['price_ht'],
                    'tva_rate' => $item['tva_rate'],
                    'total_ht' => $item['total_ht'],
                    'delivered_qty' => 0,
                ]);
            }
        });

        return redirect()->route('erp.purchase-orders.show', $purchase_order)->with('success', 'Commande d\'achat mise a jour avec succes.');
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        if ($purchase_order->deliveryNotes()->exists()) {
            return redirect()->route('erp.purchase-orders.show', $purchase_order)->withErrors(['status' => 'Cette commande a deja des receptions : elle ne peut plus etre modifiee ni supprimee.']);
        }

        $purchase_order->delete();

        return redirect()->route('erp.purchase-orders.index')->with('success', 'Commande d\'achat supprimee avec succes.');
    }

    /** Creates a receipt for what is not received yet (partial receipts allowed). */
    public function convertToDelivery(PurchaseOrder $purchase_order, DocumentNumberService $numbering, OrderFulfillmentService $fulfillment)
    {
        if (!in_array($purchase_order->status, ['draft', 'confirmed', 'partial'], true)) {
            return back()->withErrors(['status' => 'Seules les commandes d\'achat brouillon, confirmees ou partielles peuvent etre converties en bon de reception.']);
        }

        // An empty receipt (nothing counted yet) is reused instead of creating a duplicate.
        $emptyNote = DeliveryNote::where('purchase_order_id', $purchase_order->id)
            ->whereDoesntHave('items', fn ($q) => $q->where('received_qty', '>', 0))
            ->latest()
            ->first();
        if ($emptyNote) {
            return redirect()->route('erp.delivery-notes.edit', $emptyNote)->with('success', 'Un bon de reception en attente existe deja : saisissez les quantites recues.');
        }

        $remaining = $fulfillment->remainingToReceive($purchase_order);
        if ($remaining->isEmpty()) {
            return back()->withErrors(['status' => 'Toutes les quantites de cette commande sont deja receptionnees.']);
        }

        $note = DB::transaction(function () use ($numbering, $purchase_order, $remaining, $fulfillment) {
            $note = DeliveryNote::create([
                'receipt_number' => $numbering->next('delivery_notes', 'receipt_number', config('numbering.delivery_note', 'BLA')),
                'purchase_order_id' => $purchase_order->id,
                'supplier_id' => $purchase_order->supplier_id,
                'delivery_date' => now()->toDateString(),
                'status' => 'partial',
                'notes' => null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($remaining as $line) {
                DeliveryNoteItem::create([
                    'delivery_note_id' => $note->id,
                    'purchase_order_item_id' => $line['item']->id,
                    'product_id' => $line['item']->product_id,
                    'service_id' => $line['item']->service_id,
                    'item_name' => $line['item']->item_name,
                    'quantity' => $line['remaining'],
                    'received_qty' => 0,
                ]);
            }

            $fulfillment->syncPurchaseOrder($purchase_order);

            return $note;
        });

        return redirect()->route('erp.delivery-notes.edit', $note)->with('success', 'Bon de reception cree pour le reste a recevoir.');
    }
}

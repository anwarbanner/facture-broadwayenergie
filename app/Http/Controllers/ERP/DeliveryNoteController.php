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
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Services\Fulfillment\OrderFulfillmentService;
use App\Services\Numbering\DocumentNumberService;
use App\Services\Pdf\DeliveryNotePdfService;
use App\Services\Stock\StockService;
use App\Services\Invoicing\InvoiceLifecycleService;
use App\Support\FinancialHelper;
use App\Support\InvoiceLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryNoteController extends Controller
{
    use HandlesDocumentLines, FiltersDocuments, RedirectsToList;

    public function __construct(private StockService $stock, private OrderFulfillmentService $fulfillment)
    {
    }

    public function index()
    {
        $notes = $this->filterDocuments(DeliveryNote::query()->with(['supplier', 'order', 'supplierInvoice']), 'receipt_number', 'supplier')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.purchases.delivery_notes.index', compact('notes'));
    }

    public function create()
    {
        $note = new DeliveryNote();
        $suppliers = Contact::suppliers()->where('status', 'active')->orderBy('company_name')->get();
        $orders = PurchaseOrder::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();

        return view('erp.purchases.delivery_notes.create', compact('note', 'suppliers', 'orders', 'products', 'services'));
    }

    public function store(Request $request, DocumentNumberService $numbering)
    {
        $data = $request->validate([
            'receipt_number' => ['nullable', 'string', 'max:255'],
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'integer', 'exists:contacts,id'],
            'delivery_date' => ['required', 'date'],
            'status' => ['required', 'in:partial,complete'],
            'notes' => ['nullable', 'string'],
            'item_source_id' => ['nullable', 'array'],
            'item_source_id.*' => ['nullable', 'integer'],
            'item_product_id' => ['required', 'array', 'min:1'],
            'item_product_id.*' => ['nullable', 'integer', 'exists:products,id'],
            'item_service_id' => ['nullable', 'array'],
            'item_service_id.*' => ['nullable', 'integer', 'exists:services,id'],
            'item_name' => ['nullable', 'array'],
            'item_name.*' => ['nullable', 'string', 'max:255'],
            'item_mode' => ['nullable', 'array'],
            'item_mode.*' => ['nullable', 'in:product,service,manual'],
            'item_quantity' => ['required', 'array', 'min:1'],
            'item_quantity.*' => ['nullable', 'numeric', 'min:0.001'],
            'item_received_qty' => ['nullable', 'array'],
            'item_received_qty.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lineErrors = $this->checkLineChoiceWithManual(
            $data['item_product_id'] ?? [],
            $data['item_service_id'] ?? [],
            $data['item_name'] ?? [],
            $data['item_mode'] ?? [],
            'item_name',
            'item_mode'
        );
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        $items = $this->normalizeItems($data, PurchaseOrder::find($data['purchase_order_id']));
        if (empty($items)) {
            return back()->withErrors(['item_product_id' => 'Ajoutez au moins un article valide.'])->withInput();
        }

        $note = DB::transaction(function () use ($data, $items, $numbering) {
            $note = DeliveryNote::create([
                'receipt_number' => $numbering->next('delivery_notes', 'receipt_number', config('numbering.delivery_note', 'BLA')),
                'purchase_order_id' => $data['purchase_order_id'],
                'supplier_id' => $data['supplier_id'],
                'delivery_date' => $data['delivery_date'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                DeliveryNoteItem::create([
                    'delivery_note_id' => $note->id,
                    'purchase_order_item_id' => $item['purchase_order_item_id'],
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'received_qty' => $item['received_qty'],
                ]);
            }

            $this->stock->syncReceipt($note);
            $this->fulfillment->syncPurchaseOrder($note->order()->first());

            return $note;
        });

        return $this->createdRedirect('delivery-notes', $note, 'Bon de reception cree avec succes.');
    }

    public function show(DeliveryNote $delivery_note)
    {
        $delivery_note->load(['supplier', 'order', 'items.product', 'items.service', 'supplierInvoice']);

        return view('erp.purchases.delivery_notes.show', ['note' => $delivery_note]);
    }

    public function downloadPdf(DeliveryNote $delivery_note, DeliveryNotePdfService $pdfService)
    {
        $delivery_note->load('items.service');
        return $pdfService->download($delivery_note);
    }

    public function edit(DeliveryNote $delivery_note)
    {
        $suppliers = Contact::suppliers()->where('status', 'active')->orderBy('company_name')->get();
        $orders = PurchaseOrder::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();
        $delivery_note->load('items');

        return view('erp.purchases.delivery_notes.edit', ['note' => $delivery_note, 'suppliers' => $suppliers, 'orders' => $orders, 'products' => $products, 'services' => $services]);
    }

    public function update(Request $request, DeliveryNote $delivery_note)
    {
        // Once invoiced, the bon de reception is the basis of the invoice: it must not change any more.
        if (SupplierInvoice::issuedOrDraftFor('delivery_note_id', $delivery_note->id)->exists()) {
            return redirect()->route('erp.delivery-notes.show', $delivery_note)->withErrors(['status' => 'Ce bon de reception est deja facture : il ne peut plus etre modifie ni supprime.']);
        }

        $data = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'integer', 'exists:contacts,id'],
            'delivery_date' => ['required', 'date'],
            'status' => ['required', 'in:partial,complete'],
            'notes' => ['nullable', 'string'],
            'item_source_id' => ['nullable', 'array'],
            'item_source_id.*' => ['nullable', 'integer'],
            'item_product_id' => ['required', 'array', 'min:1'],
            'item_product_id.*' => ['nullable', 'integer', 'exists:products,id'],
            'item_service_id' => ['nullable', 'array'],
            'item_service_id.*' => ['nullable', 'integer', 'exists:services,id'],
            'item_name' => ['nullable', 'array'],
            'item_name.*' => ['nullable', 'string', 'max:255'],
            'item_mode' => ['nullable', 'array'],
            'item_mode.*' => ['nullable', 'in:product,service,manual'],
            'item_quantity' => ['required', 'array', 'min:1'],
            'item_quantity.*' => ['nullable', 'numeric', 'min:0.001'],
            'item_received_qty' => ['nullable', 'array'],
            'item_received_qty.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lineErrors = $this->checkLineChoiceWithManual(
            $data['item_product_id'] ?? [],
            $data['item_service_id'] ?? [],
            $data['item_name'] ?? [],
            $data['item_mode'] ?? [],
            'item_name',
            'item_mode'
        );
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }

        $items = $this->normalizeItems($data, PurchaseOrder::find($data['purchase_order_id']));
        if (empty($items)) {
            return back()->withErrors(['item_product_id' => 'Ajoutez au moins un article valide.'])->withInput();
        }

        DB::transaction(function () use ($delivery_note, $data, $items) {
            $previousOrderId = $delivery_note->purchase_order_id;
            $delivery_note->update([
                'purchase_order_id' => $data['purchase_order_id'],
                'supplier_id' => $data['supplier_id'],
                'delivery_date' => $data['delivery_date'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'updated_by' => Auth::id(),
            ]);

            $delivery_note->items()->delete();
            foreach ($items as $item) {
                DeliveryNoteItem::create([
                    'delivery_note_id' => $delivery_note->id,
                    'purchase_order_item_id' => $item['purchase_order_item_id'],
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'received_qty' => $item['received_qty'],
                ]);
            }

            // Only the difference with what was already received for this note is applied.
            $this->stock->syncReceipt($delivery_note);
            $this->fulfillment->syncPurchaseOrder($delivery_note->order()->first());
            if ((int) $previousOrderId !== (int) $delivery_note->purchase_order_id) {
                $this->fulfillment->syncPurchaseOrder(PurchaseOrder::find($previousOrderId));
            }
        });

        return redirect()->route('erp.delivery-notes.show', $delivery_note)->with('success', 'Bon de reception mis a jour avec succes.');
    }

    public function destroy(DeliveryNote $delivery_note)
    {
        // Once invoiced, the bon de reception is the basis of the invoice: it must not change any more.
        if (SupplierInvoice::issuedOrDraftFor('delivery_note_id', $delivery_note->id)->exists()) {
            return redirect()->route('erp.delivery-notes.show', $delivery_note)->withErrors(['status' => 'Ce bon de reception est deja facture : il ne peut plus etre modifie ni supprime.']);
        }

        DB::transaction(function () use ($delivery_note) {
            $this->stock->syncReceipt($delivery_note, deleting: true);
            $delivery_note->delete();
            $this->fulfillment->syncPurchaseOrder($delivery_note->order()->first());
        });

        return redirect()->route('erp.delivery-notes.index')->with('success', 'Bon de reception supprime avec succes.');
    }

    public function convertToInvoice(DeliveryNote $delivery_note, DocumentNumberService $numbering)
    {
        if (!in_array($delivery_note->status, ['partial', 'complete'], true)) {
            return back()->withErrors(['status' => 'Le bon de reception doit etre partiel ou complet avant conversion en facture.']);
        }

        $existingInvoice = SupplierInvoice::issuedOrDraftFor('delivery_note_id', $delivery_note->id)->latest()->first();
        if ($existingInvoice) {
            return redirect()->route('erp.supplier-invoices.edit', $existingInvoice)->with('success', 'Une facture fournisseur existe deja pour ce bon de reception.');
        }

        $delivery_note->load(['order.items', 'items.purchaseOrderItem']);

        // One VAT rate per document: the order's, applied to the invoice total HT.
        $tvaRate = (float) ($delivery_note->order?->tva_rate ?? \App\Support\Branding::defaultVatRate());
        $totalHt = 0.0;
        $preparedItems = [];

        foreach ($delivery_note->items as $item) {
            // Bill what was really received: a line received at 0 is not billed.
            $qty = (float) $item->received_qty;
            if ($qty <= 0) {
                continue;
            }

            // Price of the exact order line; legacy lines without link fall back to matching.
            $orderItem = $item->purchaseOrderItem;
            if (!$orderItem && $item->product_id) {
                $orderItem = $delivery_note->order?->items?->firstWhere('product_id', $item->product_id);
            }
            if (!$orderItem && $item->service_id) {
                $orderItem = $delivery_note->order?->items?->firstWhere('service_id', $item->service_id);
            }
            if (!$orderItem && $item->item_name) {
                $orderItem = $delivery_note->order?->items?->firstWhere('item_name', $item->item_name);
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
                'delivery_note_item_id' => $item->id,
            ];

            $totalHt += $lineHt;
        }

        if (empty($preparedItems)) {
            return back()->withErrors(['items' => 'Aucune ligne receptionnee valide a convertir en facture.']);
        }

        ['total_ht' => $totalHt, 'total_tva' => $totalTva, 'total_ttc' => $totalTtc] = FinancialHelper::documentTotals($totalHt, $tvaRate);
        $dueDate = now()->addDays(30)->toDateString();
        $amountPaid = FinancialHelper::paidAmount($totalTtc, 0);
        $balance = FinancialHelper::balance($totalTtc, $amountPaid);
        $status = FinancialHelper::invoiceStatus($totalTtc, $amountPaid, $dueDate);

        $invoice = DB::transaction(function () use ($delivery_note, $numbering, $tvaRate, $totalHt, $totalTva, $totalTtc, $dueDate, $amountPaid, $balance, $status, $preparedItems) {
            $invoice = SupplierInvoice::create([
                'invoice_number' => InvoiceLifecycleService::provisionalNumber(),
                'document_type' => InvoiceLifecycle::TYPE_INVOICE,
                'state' => InvoiceLifecycle::STATE_DRAFT,
                'supplier_id' => $delivery_note->supplier_id,
                'purchase_order_id' => $delivery_note->purchase_order_id,
                'delivery_note_id' => $delivery_note->id,
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
                'currency' => $delivery_note->order->currency ?? \App\Support\Branding::defaultCurrency(),
                'exchange_rate' => 1,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($preparedItems as $item) {
                SupplierInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'delivery_note_item_id' => $item['delivery_note_item_id'],
                    'product_id' => $item['product_id'],
                    'service_id' => $item['service_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price_ht' => $item['price_ht'],
                    'tva_rate' => $item['tva_rate'],
                    'total_ht' => $item['total_ht'],
                ]);
            }

            $delivery_note->update(['status' => 'complete', 'updated_by' => Auth::id()]);
            $this->stock->syncReceipt($delivery_note);
            $this->fulfillment->syncPurchaseOrder($delivery_note->order()->first());

            return $invoice;
        });

        return redirect()->route('erp.supplier-invoices.edit', $invoice)->with('success', 'Facture fournisseur creee depuis le bon de reception.');
    }

    private function normalizeItems(array $data, ?PurchaseOrder $order = null): array
    {
        $items = [];
        $usedSources = [];
        $sourceIds = $data['item_source_id'] ?? [];

        $productIds = $data['item_product_id'] ?? [];
        $serviceIds = $data['item_service_id'] ?? [];
        $itemNames = $data['item_name'] ?? [];
        $quantities = $data['item_quantity'] ?? [];
        $received = $data['item_received_qty'] ?? [];

        $rowCount = max(count($productIds), count($serviceIds), count($itemNames), count($quantities), count($received));

        for ($i = 0; $i < $rowCount; $i++) {
            $productId = $productIds[$i] ?? null;
            $serviceId = $serviceIds[$i] ?? null;
            $itemName = trim((string) ($itemNames[$i] ?? ''));
            $qty = (float) ($quantities[$i] ?? 0);
            $receivedQty = (float) ($received[$i] ?? $qty);

            if (!$productId && !$serviceId && $itemName === '') {
                continue;
            }

            if ($qty <= 0) {
                continue;
            }

            if ($receivedQty < 0) {
                $receivedQty = 0;
            }

            $sourceId = $this->fulfillment->purchaseSourceFor(
                $order,
                !empty($sourceIds[$i]) ? (int) $sourceIds[$i] : null,
                $productId ? (int) $productId : null,
                $serviceId ? (int) $serviceId : null,
                $itemName !== '' ? $itemName : null,
                $usedSources
            );
            if ($sourceId) {
                $usedSources[] = $sourceId;
            }

            $items[] = [
                'purchase_order_item_id' => $sourceId,
                'product_id' => $productId ? (int) $productId : null,
                'service_id' => $serviceId ? (int) $serviceId : null,
                'item_name' => $itemName !== '' ? $itemName : null,
                'quantity' => $qty,
                'received_qty' => $receivedQty,
            ];
        }

        return $items;
    }
}

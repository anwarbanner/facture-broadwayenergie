<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Controllers\ERP\Concerns\FiltersDocuments;
use App\Http\Controllers\ERP\Concerns\HandlesDocumentLines;
use App\Http\Controllers\ERP\Concerns\IssuesCreditNotes;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\DeliveryNote;
use App\Models\Products;
use App\Models\Service;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Services\Invoicing\InvoiceLifecycleService;
use App\Services\Invoicing\PaymentService;
use App\Services\Pdf\SupplierInvoicePdfService;
use App\Support\Branding;
use App\Support\InvoiceLifecycle;
use App\Support\DocumentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierInvoiceController extends Controller
{
    use HandlesDocumentLines, FiltersDocuments, RedirectsToList, IssuesCreditNotes;

    public function index()
    {
        $invoices = $this->filterDocuments(SupplierInvoice::query()->with(['supplier', 'order', 'deliveryNote', 'quote']), 'invoice_number', 'supplier')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.purchases.supplier_invoices.index', compact('invoices'));
    }

    public function create()
    {
        $invoice = new SupplierInvoice();
        $suppliers = Contact::suppliers()->where('status', 'active')->orderBy('company_name')->get();
        $orders = PurchaseOrder::latest()->get();
        $notes = DeliveryNote::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();

        return view('erp.purchases.supplier_invoices.create', compact('invoice', 'suppliers', 'orders', 'notes', 'products', 'services'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge([
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'supplier_id' => DocumentRules::contact(),
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'delivery_note_id' => ['nullable', 'integer', 'exists:delivery_notes,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable'], // ignored: payment status is computed
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount_paid' => ['nullable'], // ignored: payments are recorded after validation
        ], DocumentRules::pricedLines()));

        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }


        $invoice = DB::transaction(function () use ($data, $totals, $items) {
            $invoice = SupplierInvoice::create([
                // Definitive number is assigned at validation (gap-free sequence).
                'invoice_number' => InvoiceLifecycleService::provisionalNumber(),
                'document_type' => InvoiceLifecycle::TYPE_INVOICE,
                'state' => InvoiceLifecycle::STATE_DRAFT,
                'supplier_id' => $this->contactId($data['supplier_id'], 'supplier'),
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'delivery_note_id' => $data['delivery_note_id'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                ...$this->documentAmounts($data, $totals, invoice: true),
                'amount_paid' => 0,
                'balance_remaining' => $totals['total_ttc'],
                'status' => 'unpaid',
                'payment_terms' => $data['payment_terms'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                SupplierInvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
            }

            return $invoice;
        });

        // Simple mode: no draft step for finance users, the invoice is validated and opened (Imprimer / Payer).
        if (Branding::simpleMode() && Auth::user()?->can('manage-finance')) {
            app(InvoiceLifecycleService::class)->validate($invoice);

            return redirect()->route('erp.supplier-invoices.show', $invoice)->with('success', 'Facture fournisseur ' . $invoice->invoice_number . ' enregistree.');
        }

        return $this->createdRedirect('supplier-invoices', $invoice, 'Brouillon de facture cree. Validez-le pour lui attribuer son numero definitif.');
    }

    public function show(SupplierInvoice $supplier_invoice)
    {
        $supplier_invoice->load(['paymentAllocations.payment', 'supplier', 'order', 'deliveryNote', 'items.product', 'items.service', 'creditNote', 'creditNotes', 'creditedInvoice', 'quote']);

        return view('erp.purchases.supplier_invoices.show', ['invoice' => $supplier_invoice]);
    }

    public function edit(SupplierInvoice $supplier_invoice)
    {
        if ($locked = $this->rejectIfNotDraft($supplier_invoice)) {
            return $locked;
        }

        $suppliers = Contact::suppliers()->where('status', 'active')->orderBy('company_name')->get();
        $orders = PurchaseOrder::latest()->get();
        $notes = DeliveryNote::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();
        $supplier_invoice->load('items');

        return view('erp.purchases.supplier_invoices.edit', ['invoice' => $supplier_invoice, 'suppliers' => $suppliers, 'orders' => $orders, 'notes' => $notes, 'products' => $products, 'services' => $services]);
    }

    public function update(Request $request, SupplierInvoice $supplier_invoice)
    {
        if ($locked = $this->rejectIfNotDraft($supplier_invoice)) {
            return $locked;
        }

        $data = $request->validate(array_merge([
            'supplier_id' => DocumentRules::contact(),
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'delivery_note_id' => ['nullable', 'integer', 'exists:delivery_notes,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable'], // ignored: payment status is computed
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount_paid' => ['nullable'], // ignored: payments are recorded after validation
        ], DocumentRules::pricedLines()));

        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }


        DB::transaction(function () use ($supplier_invoice, $data, $totals, $items) {
            $supplier_invoice->update([
                'supplier_id' => $this->contactId($data['supplier_id'], 'supplier'),
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'delivery_note_id' => $data['delivery_note_id'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                ...$this->documentAmounts($data, $totals, invoice: true),
                'amount_paid' => 0,
                'balance_remaining' => $totals['total_ttc'],
                'status' => 'unpaid',
                'payment_terms' => $data['payment_terms'] ?? null,
                'updated_by' => Auth::id(),
            ]);

            $supplier_invoice->items()->delete();
            foreach ($items as $item) {
                SupplierInvoiceItem::create(array_merge($item, ['invoice_id' => $supplier_invoice->id]));
            }
        });

        return redirect()->route('erp.supplier-invoices.show', $supplier_invoice)->with('success', 'Facture fournisseur mise a jour avec succes.');
    }

    public function destroy(SupplierInvoice $supplier_invoice)
    {
        if ($locked = $this->rejectIfNotDraft($supplier_invoice)) {
            return $locked;
        }

        DB::transaction(function () use ($supplier_invoice) {
            $supplier_invoice->delete();
        });

        return redirect()->route('erp.supplier-invoices.index')->with('success', 'Facture fournisseur supprimee avec succes.');
    }

    public function downloadPdf(SupplierInvoice $supplier_invoice, SupplierInvoicePdfService $pdfService)
    {
        $supplier_invoice->load('items.service');
        return $pdfService->download($supplier_invoice);
    }

    /** "Imprimer": the PDF opens in the browser, ready to print. */
    public function printPdf(SupplierInvoice $supplier_invoice, SupplierInvoicePdfService $pdfService)
    {
        return $pdfService->stream($supplier_invoice);
    }

    public function validateInvoice(SupplierInvoice $supplier_invoice, InvoiceLifecycleService $lifecycle)
    {
        $lifecycle->validate($supplier_invoice);

        return redirect()->route('erp.supplier-invoices.show', $supplier_invoice)->with('success', 'Facture validee sous le numero ' . $supplier_invoice->invoice_number . '.');
    }

    /** Settles a validated invoice, or refunds a credit note (same form, opposite direction). */
    public function recordPayment(Request $request, SupplierInvoice $supplier_invoice, PaymentService $payments)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $payments->recordForInvoice($supplier_invoice, (float) $data['amount'], $data);

        return redirect()->route('erp.supplier-invoices.show', $supplier_invoice)
            ->with('success', $supplier_invoice->isCreditNote() ? 'Remboursement enregistre.' : 'Paiement enregistre.');
    }

    public function creditNote(Request $request, SupplierInvoice $supplier_invoice, InvoiceLifecycleService $lifecycle)
    {
        return $this->issueCreditNote($request, $supplier_invoice, $lifecycle, 'erp.supplier-invoices');
    }

    private function rejectIfNotDraft(SupplierInvoice $invoice)
    {
        if ($invoice->isDraft()) {
            return null;
        }

        return redirect()->route('erp.supplier-invoices.show', $invoice)
            ->with('error', 'Ce document est valide : il ne peut plus etre modifie ni supprime. Pour le corriger, creez un avoir.');
    }
}

<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Controllers\ERP\Concerns\FiltersDocuments;
use App\Http\Controllers\ERP\Concerns\HandlesDocumentLines;
use App\Http\Controllers\ERP\Concerns\IssuesCreditNotes;
use App\Http\Requests\UpsertClientInvoiceRequest;
use App\Models\ClientInvoice;
use App\Models\ClientInvoiceItem;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\Delivery;
use App\Models\Products;
use App\Models\Service;
use App\Models\SalesOrder;
use App\Services\Invoicing\InvoiceLifecycleService;
use App\Services\Invoicing\PaymentService;
use App\Services\Pdf\SalesInvoicePdfService;
use App\Support\Branding;
use App\Support\InvoiceLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientInvoiceController extends Controller
{
    use HandlesDocumentLines, FiltersDocuments, RedirectsToList, IssuesCreditNotes;

    public function index()
    {
        $invoices = $this->filterDocuments(ClientInvoice::query()->with(['client', 'order', 'delivery', 'quote']), 'invoice_number', 'client')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.client_invoices.index', compact('invoices'));
    }

    public function create()
    {
        $invoice = new ClientInvoice();
        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();
        $orders = SalesOrder::latest()->get();
        $deliveries = Delivery::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();

        return view('erp.client_invoices.create', compact('invoice', 'clients', 'orders', 'deliveries', 'products', 'services'));
    }

    public function store(UpsertClientInvoiceRequest $request)
    {
        $data = $request->validated();
        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }


        $invoice = DB::transaction(function () use ($data, $totals, $items) {
            $invoice = ClientInvoice::create([
                // Definitive number is assigned at validation (gap-free sequence).
                'invoice_number' => InvoiceLifecycleService::provisionalNumber(),
                'document_type' => InvoiceLifecycle::TYPE_INVOICE,
                'state' => InvoiceLifecycle::STATE_DRAFT,
                'client_id' => $this->contactId($data['client_id'], 'client'),
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'delivery_id' => $data['delivery_id'] ?? null,
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
                ClientInvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
            }

            return $invoice;
        });

        // Simple mode ("Facture sans devis"): no draft step for finance users, the invoice is validated and
        // opened, ready to print and to cash in.
        if (Branding::simpleMode() && Auth::user()?->can('manage-finance')) {
            app(InvoiceLifecycleService::class)->validate($invoice);

            return redirect()->route('erp.client-invoices.show', $invoice)->with('success', 'Facture ' . $invoice->invoice_number . ' creee. Vous pouvez l\'imprimer.');
        }

        return $this->createdRedirect('client-invoices', $invoice, 'Brouillon de facture cree. Validez-le pour lui attribuer son numero definitif.');
    }

    public function show(ClientInvoice $client_invoice)
    {
        $client_invoice->load(['paymentAllocations.payment', 'client', 'order', 'delivery', 'items.product', 'items.service', 'creditNote', 'creditNotes', 'creditedInvoice', 'quote']);

        return view('erp.client_invoices.show', ['invoice' => $client_invoice]);
    }

    public function edit(ClientInvoice $client_invoice)
    {
        if ($locked = $this->rejectIfNotDraft($client_invoice)) {
            return $locked;
        }

        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();
        $orders = SalesOrder::latest()->get();
        $deliveries = Delivery::latest()->get();
        $products = Products::active()->orderBy('product_name')->get();
        $services = Service::active()->orderBy('service_name')->get();
        $client_invoice->load('items');

        return view('erp.client_invoices.edit', ['invoice' => $client_invoice, 'clients' => $clients, 'orders' => $orders, 'deliveries' => $deliveries, 'products' => $products, 'services' => $services]);
    }

    public function update(UpsertClientInvoiceRequest $request, ClientInvoice $client_invoice)
    {
        if ($locked = $this->rejectIfNotDraft($client_invoice)) {
            return $locked;
        }

        $data = $request->validated();
        [$items, $totals, $lineErrors] = $this->pricedLines($data);
        if (!empty($lineErrors)) {
            return back()->withErrors($lineErrors)->withInput();
        }


        DB::transaction(function () use ($client_invoice, $data, $totals, $items) {
            $client_invoice->update([
                'client_id' => $this->contactId($data['client_id'], 'client'),
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'delivery_id' => $data['delivery_id'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                ...$this->documentAmounts($data, $totals, invoice: true),
                'amount_paid' => 0,
                'balance_remaining' => $totals['total_ttc'],
                'status' => 'unpaid',
                'payment_terms' => $data['payment_terms'] ?? null,
                'updated_by' => Auth::id(),
            ]);

            $client_invoice->items()->delete();
            foreach ($items as $item) {
                ClientInvoiceItem::create(array_merge($item, ['invoice_id' => $client_invoice->id]));
            }
        });

        return redirect()->route('erp.client-invoices.show', $client_invoice)->with('success', 'Facture client mise a jour.');
    }

    public function destroy(ClientInvoice $client_invoice)
    {
        if ($locked = $this->rejectIfNotDraft($client_invoice)) {
            return $locked;
        }

        DB::transaction(function () use ($client_invoice) {
            $client_invoice->delete();
        });

        return redirect()->route('erp.client-invoices.index')->with('success', 'Brouillon de facture supprime.');
    }

    public function downloadPdf(ClientInvoice $client_invoice, SalesInvoicePdfService $pdfService)
    {
        $client_invoice->load('items.service');
        return $pdfService->download($client_invoice);
    }

    /** "Imprimer": the PDF opens in the browser, ready to print. */
    public function printPdf(ClientInvoice $client_invoice, SalesInvoicePdfService $pdfService)
    {
        return $pdfService->stream($client_invoice);
    }

    public function validateInvoice(ClientInvoice $client_invoice, InvoiceLifecycleService $lifecycle)
    {
        $lifecycle->validate($client_invoice);

        return redirect()->route('erp.client-invoices.show', $client_invoice)->with('success', 'Facture validee sous le numero ' . $client_invoice->invoice_number . '.');
    }

    /** Settles a validated invoice, or refunds a credit note (same form, opposite direction). */
    public function recordPayment(Request $request, ClientInvoice $client_invoice, PaymentService $payments)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $payments->recordForInvoice($client_invoice, (float) $data['amount'], $data);

        return redirect()->route('erp.client-invoices.show', $client_invoice)
            ->with('success', $client_invoice->isCreditNote() ? 'Remboursement enregistre.' : 'Paiement enregistre.');
    }

    public function creditNote(Request $request, ClientInvoice $client_invoice, InvoiceLifecycleService $lifecycle)
    {
        return $this->issueCreditNote($request, $client_invoice, $lifecycle, 'erp.client-invoices');
    }

    private function rejectIfNotDraft(ClientInvoice $invoice)
    {
        if ($invoice->isDraft()) {
            return null;
        }

        return redirect()->route('erp.client-invoices.show', $invoice)
            ->with('error', 'Ce document est valide : il ne peut plus etre modifie ni supprime. Pour le corriger, creez un avoir.');
    }
}

<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\SupplierInvoice;
use App\Services\Invoicing\PaymentService;
use App\Support\InvoiceLifecycle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    use RedirectsToList;

    public function __construct(private PaymentService $payments)
    {
    }

    public function index(Request $request)
    {
        $payments = Payment::query()
            ->with(['contact', 'allocations.clientInvoice', 'allocations.supplierInvoice'])
            ->when($request->query('contact_id'), fn ($q, $id) => $q->where('contact_id', $id))
            ->when($request->query('direction'), fn ($q, $direction) => $q->where('direction', $direction))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('erp.payments.index', compact('payments'));
    }

    /** Grouped settlement: one payment spread over several open invoices of the same client or supplier. */
    public function create(Request $request)
    {
        $side = $request->query('side') === 'supplier' ? 'supplier' : 'client';
        $contacts = ($side === 'client' ? Contact::clients() : Contact::suppliers())->orderBy('company_name')->get();
        $contact = $request->query('contact_id') ? $contacts->firstWhere('id', (int) $request->query('contact_id')) : null;

        $openInvoices = collect();
        if ($contact) {
            $model = $side === 'client' ? ClientInvoice::class : SupplierInvoice::class;
            $openInvoices = $model::issued()
                ->where($side === 'client' ? 'client_id' : 'supplier_id', $contact->id)
                ->where('balance_remaining', '>', 0)
                ->orderBy('due_date')
                ->orderBy('invoice_date')
                ->get();
        }

        return view('erp.payments.create', compact('side', 'contacts', 'contact', 'openInvoices'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'side' => ['required', Rule::in(['client', 'supplier'])],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $model = $data['side'] === 'client' ? ClientInvoice::class : SupplierInvoice::class;
        $invoices = $model::whereIn('id', array_keys($data['amounts']))
            ->where('document_type', InvoiceLifecycle::TYPE_INVOICE)
            ->get()
            ->keyBy('id');

        $allocations = [];
        foreach ($data['amounts'] as $invoiceId => $amount) {
            if ((float) $amount > 0 && $invoices->has((int) $invoiceId)) {
                $allocations[] = ['invoice' => $invoices[(int) $invoiceId], 'amount' => (float) $amount];
            }
        }

        $payment = $this->payments->record(Contact::findOrFail($data['contact_id']), $allocations, $data);

        return $this->createdRedirect('payments', $payment, 'Reglement de ' . number_format((float) $payment->amount, 2, '.', ' ') . ' enregistre sur ' . count($allocations) . ' facture(s).', ['contact_id' => $payment->contact_id]);
    }

    public function void(Request $request, Payment $payment)
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:255']]);
        $this->payments->void($payment, $data['void_reason']);

        return back()->with('success', 'Reglement annule. Les soldes des factures concernees ont ete recalcules.');
    }
}

<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Models\Contact;
use App\Services\Invoicing\StatementService;
use App\Models\SupplierStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierStatementController extends Controller
{
    use RedirectsToList;

    public function __construct(private StatementService $statements)
    {
    }

    public function index()
    {
        $statements = SupplierStatement::query()
            ->with('supplier')
            ->when(request('q'), function ($q, $term) {
                $q->whereHas('supplier', function ($sq) use ($term) {
                    $sq->where('company_name', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();


        // Figures stored at generation time can be outdated: show the live ledger totals.
        $statements->getCollection()->each(function ($statement) {
            $ledger = $this->statements->build(
                StatementService::SIDE_SUPPLIER,
                (int) $statement->supplier_id,
                \Illuminate\Support\Carbon::parse($statement->period_start)->toDateString(),
                \Illuminate\Support\Carbon::parse($statement->period_end)->toDateString()
            );
            $statement->forceFill([
                'total_invoices' => $ledger['invoice_count'],
                'total_payments' => $ledger['payments_total'],
                'balance' => $ledger['closing'],
            ]);
        });

        return view('erp.purchases.supplier_statements.index', compact('statements'));
    }

    public function create()
    {
        $statement = new SupplierStatement();
        $suppliers = Contact::suppliers()->where('status', 'active')->orderBy('company_name')->get();

        return view('erp.purchases.supplier_statements.create', compact('statement', 'suppliers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:contacts,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $ledger = $this->statements->build(StatementService::SIDE_SUPPLIER, (int) $data['supplier_id'], $data['period_start'], $data['period_end']);

        $statement = SupplierStatement::create([
            'supplier_id' => $data['supplier_id'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'total_invoices' => $ledger['invoice_count'],
            'total_payments' => $ledger['payments_total'],
            'balance' => $ledger['closing'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return $this->createdRedirect('supplier-statements', $statement, 'Releve fournisseur genere avec succes.');
    }

    public function show(SupplierStatement $supplier_statement)
    {
        $supplier_statement->load('supplier');
        $ledger = $this->statements->build(
            StatementService::SIDE_SUPPLIER,
            (int) $supplier_statement->supplier_id,
            \Illuminate\Support\Carbon::parse($supplier_statement->period_start)->toDateString(),
            \Illuminate\Support\Carbon::parse($supplier_statement->period_end)->toDateString()
        );

        return view('erp.purchases.supplier_statements.show', ['statement' => $supplier_statement, 'ledger' => $ledger]);
    }
}

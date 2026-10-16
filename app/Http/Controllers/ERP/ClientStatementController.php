<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ERP\Concerns\RedirectsToList;
use App\Http\Requests\UpsertClientStatementRequest;
use App\Models\ClientStatement;
use App\Models\Contact;
use App\Services\Invoicing\StatementService;
use Illuminate\Support\Facades\Auth;

class ClientStatementController extends Controller
{
    use RedirectsToList;

    public function __construct(private StatementService $statements)
    {
    }

    public function index()
    {
        $statements = ClientStatement::with('client')->latest()->paginate(20);


        // Figures stored at generation time can be outdated: show the live ledger totals.
        $statements->getCollection()->each(function ($statement) {
            $ledger = $this->statements->build(
                StatementService::SIDE_CLIENT,
                (int) $statement->client_id,
                \Illuminate\Support\Carbon::parse($statement->period_start)->toDateString(),
                \Illuminate\Support\Carbon::parse($statement->period_end)->toDateString()
            );
            $statement->forceFill([
                'total_invoices' => $ledger['invoice_count'],
                'total_payments' => $ledger['payments_total'],
                'balance' => $ledger['closing'],
            ]);
        });

        return view('erp.client_statements.index', compact('statements'));
    }

    public function create()
    {
        $statement = new ClientStatement();
        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();

        return view('erp.client_statements.create', compact('statement', 'clients'));
    }

    public function store(UpsertClientStatementRequest $request)
    {
        $data = $request->validated();

        $ledger = $this->statements->build(StatementService::SIDE_CLIENT, (int) $data['client_id'], $data['period_start'], $data['period_end']);

        $statement = ClientStatement::create([
            'client_id' => $data['client_id'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'total_invoices' => $ledger['invoice_count'],
            'total_payments' => $ledger['payments_total'],
            'balance' => $ledger['closing'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return $this->createdRedirect('client-statements', $statement, 'Releve client genere.');
    }

    public function show(ClientStatement $client_statement)
    {
        $client_statement->load('client');
        $ledger = $this->statements->build(
            StatementService::SIDE_CLIENT,
            (int) $client_statement->client_id,
            $client_statement->period_start->toDateString(),
            $client_statement->period_end->toDateString()
        );

        return view('erp.client_statements.show', ['statement' => $client_statement, 'ledger' => $ledger]);
    }

    public function edit(ClientStatement $client_statement)
    {
        $clients = Contact::clients()->where('status', 'active')->orderBy('company_name')->get();

        return view('erp.client_statements.edit', ['statement' => $client_statement, 'clients' => $clients]);
    }

    public function update(UpsertClientStatementRequest $request, ClientStatement $client_statement)
    {
        $data = $request->validated();
        $ledger = $this->statements->build(StatementService::SIDE_CLIENT, (int) $data['client_id'], $data['period_start'], $data['period_end']);

        $client_statement->update([
            'client_id' => $data['client_id'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'total_invoices' => $ledger['invoice_count'],
            'total_payments' => $ledger['payments_total'],
            'balance' => $ledger['closing'],
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('erp.client-statements.index')->with('success', 'Releve client mis a jour.');
    }

    public function destroy(ClientStatement $client_statement)
    {
        $client_statement->delete();

        return redirect()->route('erp.client-statements.index')->with('success', 'Releve client supprime.');
    }

}

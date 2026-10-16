@extends('layouts.master')

@section('title', 'Releves clients')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Releves clients</h4>
        <a href="{{ route('erp.client-statements.create') }}" class="btn btn-primary"><i class="fe fe-plus" aria-hidden="true"></i> Generer un releve</a>
    </div>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Client</th><th>Debut</th><th>Fin</th><th>Total factures</th><th>Total paiements</th><th>Solde</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($statements as $statement)
                <tr>
                    <td>{{ $statement->client->company_name ?? '-' }}</td>
                    <td>{{ \App\Support\Format::date($statement->period_start) }}</td>
                    <td>{{ \App\Support\Format::date($statement->period_end) }}</td>
                    <td>{{ $statement->total_invoices }}</td>
                    <td class="erp-num">{{ \App\Support\Format::money($statement->total_payments) }}</td>
                    <td class="erp-num">{{ \App\Support\Format::money($statement->balance) }}</td>
                    <td class="erp-actions">
                        <a href="{{ route('erp.client-statements.show', $statement) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                        <a href="{{ route('erp.client-statements.edit', $statement) }}" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        <form method="POST" action="{{ route('erp.client-statements.destroy', $statement) }}" onsubmit="return confirm('Supprimer ce releve ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">Aucun releve trouve</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $statements->links() }}
    </div></div>
</div>
@endsection




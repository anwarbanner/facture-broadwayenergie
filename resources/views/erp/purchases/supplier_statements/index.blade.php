@extends('layouts.master')

@section('title', 'Releves fournisseurs')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Releves fournisseurs</h4>
        <a href="{{ route('erp.supplier-statements.create') }}" class="btn btn-primary"><i class="fe fe-plus" aria-hidden="true"></i> Nouveau releve</a>
    </div>
    <form method="GET" class="mb-3 erp-filter">
        <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Rechercher fournisseur" aria-label="Rechercher fournisseur">
    </form>
    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Fournisseur</th><th>Periode</th><th>Nb factures</th><th>Total paiements</th><th>Solde</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($statements as $statement)
                <tr>
                    <td>{{ $statement->supplier->company_name ?? '-' }}</td>
                    <td>{{ $statement->period_start }} - {{ $statement->period_end }}</td>
                    <td>{{ $statement->total_invoices }}</td>
                    <td class="erp-num">{{ \App\Support\Format::money($statement->total_payments) }}</td>
                    <td class="erp-num">{{ \App\Support\Format::money($statement->balance) }}</td>
                    <td class="erp-actions">
                        <a href="{{ route('erp.supplier-statements.show', $statement) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Aucun releve fournisseur trouve</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $statements->links() }}
    </div></div>
</div>
@endsection



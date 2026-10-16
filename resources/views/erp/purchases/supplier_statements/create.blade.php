@extends('layouts.master')

@section('title', 'Nouveau releve fournisseur')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Generer un releve fournisseur</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.supplier-statements.store') }}">
            @csrf
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Fournisseur</label>
                    <select name="supplier_id" class="form-control" required>
                        <option value="">-- Selectionner --</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Periode debut</label>
                    <input type="date" name="period_start" class="form-control" value="{{ now()->startOfMonth()->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Periode fin</label>
                    <input type="date" name="period_end" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                </div>
            </div>
            <button class="btn btn-primary" type="submit"><i class="fe fe-plus" aria-hidden="true"></i> Generer</button>
            <a href="{{ route('erp.supplier-statements.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
        </form>
    </div></div>
</div>
@endsection

@extends('layouts.master')

@section('title', 'Nouvelle facture fournisseur')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">{{ \App\Support\Branding::simpleMode() ? 'Facture fournisseur sans devis' : 'Nouvelle facture fournisseur' }}</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.supplier-invoices.store') }}">
            @include('erp.purchases.supplier_invoices._form')
        </form>
    </div></div>
</div>
@endsection

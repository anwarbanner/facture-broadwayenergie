@extends('layouts.master')

@section('title', 'Modifier facture fournisseur')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier facture fournisseur</h4>
    <form method="POST" action="{{ route('erp.supplier-invoices.update', $invoice) }}">
        @method('PUT')
        @include('erp.purchases.supplier_invoices._form')
    </form>
</div>
@endsection

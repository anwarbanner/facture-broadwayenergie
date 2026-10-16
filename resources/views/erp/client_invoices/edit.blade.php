@extends('layouts.master')

@section('title', 'Modifier la facture client')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier la facture client</h4>
    <form method="POST" action="{{ route('erp.client-invoices.update', $invoice) }}">
        @method('PUT')
        @include('erp.client_invoices._form')
    </form>
</div>
@endsection


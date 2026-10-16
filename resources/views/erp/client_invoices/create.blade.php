@extends('layouts.master')

@section('title', 'Creer une facture client')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">{{ \App\Support\Branding::simpleMode() ? 'Facture sans devis' : 'Creer une facture client' }}</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.client-invoices.store') }}">
            @include('erp.client_invoices._form')
        </form>
    </div></div>
</div>
@endsection


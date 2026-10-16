@extends('layouts.master')

@section('title', 'Modifier la demande d\'achat')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">{{ \App\Support\Branding::simpleMode() ? 'Modifier le devis fournisseur' : 'Modifier la demande d\'achat' }}</h4>
    <form method="POST" action="{{ route('erp.purchase-quotes.update', $quote) }}">
        @method('PUT')
        @include('erp.purchases.quotes._form')
    </form>
</div>
@endsection

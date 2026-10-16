@extends('layouts.master')

@section('title', 'Nouvelle demande d\'achat')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">{{ \App\Support\Branding::simpleMode() ? 'Nouveau devis fournisseur' : 'Nouvelle demande d\'achat' }}</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.purchase-quotes.store') }}">
            @include('erp.purchases.quotes._form')
        </form>
    </div></div>
</div>
@endsection

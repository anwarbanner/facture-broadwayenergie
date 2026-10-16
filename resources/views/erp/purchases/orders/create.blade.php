@extends('layouts.master')

@section('title', 'Nouvelle commande achat')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Nouvelle commande achat</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.purchase-orders.store') }}">
            @include('erp.purchases.orders._form')
        </form>
    </div></div>
</div>
@endsection

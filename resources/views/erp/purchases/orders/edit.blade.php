@extends('layouts.master')

@section('title', 'Modifier commande achat')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier commande achat</h4>
    <form method="POST" action="{{ route('erp.purchase-orders.update', $order) }}">
        @method('PUT')
        @include('erp.purchases.orders._form')
    </form>
</div>
@endsection

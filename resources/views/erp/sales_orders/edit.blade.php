@extends('layouts.master')

@section('title', 'Modifier la commande client')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier la commande client</h4>
    <form method="POST" action="{{ route('erp.sales-orders.update', $order) }}">
        @method('PUT')
        @include('erp.sales_orders._form')
    </form>
</div>
@endsection


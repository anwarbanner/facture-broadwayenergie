@extends('layouts.master')

@section('title', 'Creer une commande client')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Creer une commande client</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.sales-orders.store') }}">
            @include('erp.sales_orders._form')
        </form>
    </div></div>
</div>
@endsection


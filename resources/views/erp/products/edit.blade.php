@extends('layouts.master')

@section('title', 'Modifier le produit')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier le produit</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.products.update', $product) }}">
            @method('PUT')
            @include('erp.products._form')
        </form>
    </div></div>
</div>
@endsection


@extends('layouts.master')

@section('title', 'Creer un produit')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">{{ \App\Support\Branding::simpleMode() ? 'Nouveau produit' : 'Creer un produit' }}</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.products.store') }}">
            @include('erp.products._form')
        </form>
    </div></div>
</div>
@endsection


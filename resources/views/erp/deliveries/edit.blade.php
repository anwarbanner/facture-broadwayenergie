@extends('layouts.master')

@section('title', 'Modifier la livraison')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier la livraison</h4>
    <form method="POST" action="{{ route('erp.deliveries.update', $delivery) }}">
        @method('PUT')
        @include('erp.deliveries._form')
    </form>
</div>
@endsection


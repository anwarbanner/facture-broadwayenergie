@extends('layouts.master')

@section('title', 'Modifier le devis client')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier le devis client</h4>
    <form method="POST" action="{{ route('erp.sales-quotes.update', $quote) }}">
        @method('PUT')
        @include('erp.sales_quotes._form')
    </form>
</div>
@endsection


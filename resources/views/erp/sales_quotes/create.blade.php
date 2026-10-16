@extends('layouts.master')

@section('title', 'Creer un devis client')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Creer un devis client</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.sales-quotes.store') }}">
            @include('erp.sales_quotes._form')
        </form>
    </div></div>
</div>
@endsection


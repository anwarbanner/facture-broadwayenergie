@extends('layouts.master')

@section('title', 'Creer une livraison')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Creer une livraison</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.deliveries.store') }}">
            @include('erp.deliveries._form')
        </form>
    </div></div>
</div>
@endsection


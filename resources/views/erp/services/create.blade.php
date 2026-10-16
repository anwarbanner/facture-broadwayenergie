@extends('layouts.master')

@section('title', 'Creer un service')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Creer un service</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.services.store') }}">
            @include('erp.services._form')
        </form>
    </div></div>
</div>
@endsection

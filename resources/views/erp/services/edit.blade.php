@extends('layouts.master')

@section('title', 'Modifier le service')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier le service</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.services.update', $service) }}">
            @method('PUT')
            @include('erp.services._form')
        </form>
    </div></div>
</div>
@endsection

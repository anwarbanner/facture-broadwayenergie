@extends('layouts.master')

@section('title', 'Nouveau bon de reception')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Nouveau bon de reception</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.delivery-notes.store') }}">
            @include('erp.purchases.delivery_notes._form')
        </form>
    </div></div>
</div>
@endsection

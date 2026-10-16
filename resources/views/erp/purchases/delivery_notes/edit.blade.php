@extends('layouts.master')

@section('title', 'Modifier le bon de reception')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier le bon de reception</h4>
    <form method="POST" action="{{ route('erp.delivery-notes.update', $note) }}">
        @method('PUT')
        @include('erp.purchases.delivery_notes._form')
    </form>
</div>
@endsection

@extends('layouts.master')

@section('title', 'Modifier le releve client')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier le releve client</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.client-statements.update', $statement) }}">
            @method('PUT')
            @include('erp.client_statements._form')
        </form>
    </div></div>
</div>
@endsection


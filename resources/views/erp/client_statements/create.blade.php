@extends('layouts.master')

@section('title', 'Generer un releve client')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Generer un releve client</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.client-statements.store') }}">
            @include('erp.client_statements._form')
        </form>
    </div></div>
</div>
@endsection


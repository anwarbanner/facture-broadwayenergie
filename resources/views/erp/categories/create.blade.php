@extends('layouts.master')

@section('title', 'Creer une categorie')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Creer une categorie</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.categories.store') }}">
            @include('erp.categories._form')
        </form>
    </div></div>
</div>
@endsection


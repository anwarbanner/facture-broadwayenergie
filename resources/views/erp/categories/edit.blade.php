@extends('layouts.master')

@section('title', 'Modifier la categorie')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">Modifier la categorie</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.categories.update', $category) }}">
            @method('PUT')
            @include('erp.categories._form')
        </form>
    </div></div>
</div>
@endsection


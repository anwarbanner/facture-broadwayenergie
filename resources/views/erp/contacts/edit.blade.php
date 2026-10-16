@extends('layouts.master')

@section('title', 'Modifier le contact')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">{{ \App\Support\Branding::simpleMode() ? ($contact->type === 'supplier' ? 'Modifier le fournisseur' : 'Modifier le client') : 'Modifier le contact' }}</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.contacts.update', $contact) }}">
            @method('PUT')
            @include('erp.contacts._form')
        </form>
    </div></div>
</div>
@endsection


@extends('layouts.master')

@section('title', 'Creer un contact')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">{{ \App\Support\Branding::simpleMode() ? (($contact->type ?? 'client') === 'supplier' ? 'Nouveau fournisseur' : 'Nouveau client') : 'Creer un contact' }}</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('erp.contacts.store') }}">
            @include('erp.contacts._form')
        </form>
    </div></div>
</div>
@endsection


@extends('layouts.master')

@section('title', "Modifier l'utilisateur")

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <div>
            <h4>Modifier l'utilisateur</h4>
            <small class="text-muted"><a href="{{ route('users.index') }}">Utilisateurs</a>@if($user->exists) / {{ $user->name }}@endif</small>
        </div>
    </div>
    <form method="POST" action="{{ route('users.update', $user) }}" autocomplete="off">
        @method('PUT')
        @include('users._form')
    </form>
</div>
@endsection

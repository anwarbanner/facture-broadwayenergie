@extends('layouts.master')

@section('title', 'Nouvel utilisateur')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <div>
            <h4>Nouvel utilisateur</h4>
            <small class="text-muted"><a href="{{ route('users.index') }}">Utilisateurs</a>@if($user->exists) / {{ $user->name }}@endif</small>
        </div>
    </div>
    <form method="POST" action="{{ route('users.store') }}" autocomplete="off">

        @include('users._form')
    </form>
</div>
@endsection

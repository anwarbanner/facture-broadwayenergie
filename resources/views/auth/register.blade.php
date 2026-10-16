@extends('layouts.master2')

@section('title')
ERP - Creer un nouveau compte
@stop

@section('css')
<link href="{{URL::asset('assets/plugins/sidemenu-responsive-tabs/css/sidemenu-responsive-tabs.css')}}" rel="stylesheet">
<link href="{{ \App\Support\Asset::url('assets/css/auth-pro.css') }}" rel="stylesheet">
@endsection

@section('content')
<div class="container-fluid auth-pro-page">
    <div class="row no-gutter">
        <div class="col-lg-5 auth-left">
            <div class="auth-form-shell">
                <a href="{{ route('dashboard') }}" class="auth-brand">
                    <x-brand-logo class="auth-brand-logo" />
                </a>

                @if ($errors->any())
                    <div class="alert alert-danger auth-errors">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <h2 class="auth-title">Inscription</h2>
                <p class="auth-subtitle">Creez un compte pour acceder a la plateforme ERP.</p>

                <form method="POST" action="{{ route('register') }}" class="mt-4">
                    @csrf

                    <div class="form-group">
                        <label for="name">Nom</label>
                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
                        @error('name')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                        @error('password')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirmer le mot de passe</label>
                        <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-auth-submit btn-block mt-4">Creer un compte</button>

                    <div class="mt-3 text-center">
                        <a class="auth-link" href="{{ route('login') }}">Vous avez deja un compte ? Connectez-vous</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-7 d-none d-lg-flex auth-right">
            <div class="auth-hero">
                <x-brand-logo class="auth-hero-logo" :show-name="false" />
                <h3>Bienvenue chez {{ \App\Support\Branding::companyName() }}</h3>
                <p>Demarrez rapidement avec un environnement de gestion moderne pour vos operations commerciales et financieres.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
@endsection







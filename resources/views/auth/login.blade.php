@extends('layouts.master2')

@section('title')
ERP - Connexion 
@stop

@section('css')
<!-- Sidemenu-respoansive-tabs css -->
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

                @if (Session::has('registered'))
                    <div class="alert alert-success">{{ Session::get('registered') }}</div>
                @endif
                @if (Session::has('unactive'))
                    <div class="alert alert-danger">{{ Session::get('unactive') }}</div>
                @endif
                @if (Session::has('warning'))
                    {{-- Session ended / page expired (App\Exceptions\Handler) --}}
                    <div class="alert alert-warning" role="alert">{{ Session::get('warning') }}</div>
                @endif

                <h2 class="auth-title">Connexion</h2>
                <p class="auth-subtitle">Accedez a votre espace ERP en toute securite.</p>

                <form method="POST" action="{{ route('login') }}" class="mt-4">
                    @csrf
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                        @error('email')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                        @error('password')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="auth-remember">
                        <input class="form-check-input m-0" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label mb-0" for="remember">Se souvenir de moi</label>
                    </div>

                    <button type="submit" class="btn btn-auth-submit btn-block mt-4">Connexion</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7 d-none d-lg-flex auth-right">
            <div class="auth-hero">
                <x-brand-logo class="auth-hero-logo" :show-name="false" />
                <h3>{{ \App\Support\Branding::companyName() }}</h3>
                <p>Pilotez vos ventes, achats, stocks et facturation depuis une interface unifiee et professionnelle.</p>
            </div>
        </div>
    </div>
</div>
@endsection
@section('js')
@endsection





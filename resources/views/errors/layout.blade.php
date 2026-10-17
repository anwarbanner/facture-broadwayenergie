{{--
    Error pages in French (403, 404, 419, 429, 500, 503 and the 4xx / 5xx fallbacks), instead of Laravel's
    English pages. Always two ways out: the previous page, and the site address (dashboard, or login when the
    session is over). Expected: $code, $title, $message, optional $image (assets/img/media/...).
--}}
@extends('layouts.master2')

@section('title')
{{ $title }}
@stop

@section('content')
		<div class="main-error-wrapper page page-h">
			<img src="{{ URL::asset($image ?? 'assets/img/media/404.png') }}" class="error-page" alt="Erreur {{ $code }}">
			<h2>{{ $title }}</h2>
			<h6>{{ $message }}</h6>
			<div class="d-flex flex-wrap justify-content-center" style="gap: .5rem;">
				<a class="btn btn-outline-secondary" href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">Page precedente</a>
				<a class="btn btn-outline-danger" href="{{ url('/') }}">Retour a l'accueil</a>
			</div>
		</div>
@endsection

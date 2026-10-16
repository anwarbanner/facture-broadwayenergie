<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Format::isRtl() ? 'rtl' : 'ltr' }}">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		@include('layouts.head')
	</head>

	<body class="main-body app sidebar-mini">
		@include('layouts.page-loader')
		@include('layouts.main-sidebar')
		<!-- main-content -->
		<div class="main-content app-content">
			@include('layouts.main-header')
			<!-- container -->
			<div class="container-fluid">
				@yield('page-header')
				@include('layouts.flash')
				@yield('content')
			</div>
			<!-- /container -->

			@include('layouts.footer')
		</div>
		<!-- /main-content -->

		@include('layouts.footer-scripts')

	</body>
</html>




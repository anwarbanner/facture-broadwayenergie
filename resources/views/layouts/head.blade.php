{{-- Light / dark theme before the first paint (no white flash): saved choice, otherwise the device setting. --}}
<meta name="color-scheme" content="light dark">
<script>
    (function () {
        @if(!empty($forceLightTheme))
            document.documentElement.setAttribute('data-theme', 'light');
            return;
        @endif
        var theme = null;
        try { theme = localStorage.getItem('erp-theme'); } catch (e) {}
        if (theme !== 'light' && theme !== 'dark') {
            theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
<!-- Title -->
<title>@yield("title") - {{ \App\Support\Branding::companyName() }}</title>
<link rel="icon" href="{{ \App\Support\Branding::faviconUrl() }}">
<link rel="shortcut icon" href="{{ \App\Support\Branding::faviconUrl() }}">
<!-- Icons css -->
<link href="{{ \App\Support\Asset::url('assets/css/icons-erp.css')}}" rel="stylesheet">
<!--  Custom Scroll bar-->
<link href="{{ \App\Support\Asset::url('assets/plugins/mscrollbar/jquery.mCustomScrollbar.css')}}" rel="stylesheet"/>
<!--  Sidebar css -->
<link href="{{ \App\Support\Asset::url('assets/plugins/sidebar/sidebar.css')}}" rel="stylesheet">
<!-- Sidemenu css -->
@php($cssDir = \App\Support\Format::isRtl() ? 'css-rtl' : 'css')
<link rel="stylesheet" href="{{ \App\Support\Asset::url('assets/' . $cssDir . '/sidemenu.css')}}">
<!-- Select2 css -->
<link href="{{ \App\Support\Asset::url('assets/plugins/select2/css/select2.min.css') }}" rel="stylesheet">
@yield('css')
<!--- Style css -->
<link href="{{ \App\Support\Asset::url('assets/' . $cssDir . '/style.css')}}" rel="stylesheet">
<!--- Dark-mode css -->
<link href="{{ \App\Support\Asset::url('assets/' . $cssDir . '/style-dark.css')}}" rel="stylesheet">
<!---Skinmodes css-->
<link href="{{ \App\Support\Asset::url('assets/' . $cssDir . '/skin-modes.css')}}" rel="stylesheet">
<!-- ERP Pro UI css -->
<link href="{{ \App\Support\Asset::url('assets/css/erp-pro.css')}}" rel="stylesheet">
<!-- ERP design system: buttons, page headers, responsive tables (loaded last) -->
<link href="{{ \App\Support\Asset::url('assets/css/erp-ui.css')}}" rel="stylesheet">
<link href="{{ \App\Support\Asset::url('assets/css/erp-dark.css')}}" rel="stylesheet">




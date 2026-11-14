<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('landing.meta.title', ['app' => $site['name']]))</title>
    <meta name="description" content="@yield('description', __('landing.meta.description', ['app' => $site['name']]))">
    <meta property="og:title" content="@yield('title', __('landing.meta.title', ['app' => $site['name']]))">
    <meta property="og:description" content="@yield('description', __('landing.meta.description', ['app' => $site['name']]))">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#003673">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <meta property="og:image" content="{{ url($site['logo_url'] ?? asset(config('landing.default_logo'))) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Nunito:ital,wght@0,700;0,800;0,900;1,600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    @stack('css')
</head>
<body class="bg-white font-sans text-slate-700 antialiased selection:bg-brand-600 selection:text-white">

    @include('landing.partials.navbar')

    <main>
        @yield('content')
    </main>

    @include('landing.partials.footer')

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof AOS !== 'undefined') {
                AOS.init({ duration: 700, once: true, offset: 40, easing: 'ease-out-cubic' });
            }
        });
    </script>
    @stack('js')
</body>
</html>

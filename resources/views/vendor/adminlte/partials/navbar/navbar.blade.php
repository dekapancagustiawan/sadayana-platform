@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')

<nav class="main-header navbar
    {{ config('adminlte.classes_topnav_nav', 'navbar-expand') }}
    {{ config('adminlte.classes_topnav', 'navbar-white navbar-light') }}">

    {{-- Navbar left links --}}
    <ul class="navbar-nav">
        {{-- Left sidebar toggler link --}}
        @include('adminlte::partials.navbar.menu-item-left-sidebar-toggler')

        {{-- Configured left links --}}
        @each('adminlte::partials.navbar.menu-item', $adminlte->menu('navbar-left'), 'item')

        {{-- Custom left links --}}
        @yield('content_top_nav_left')
    </ul>

    {{-- Navbar right links --}}
    <ul class="navbar-nav ml-auto">
        {{-- Dashboard Notification Bell --}}
        @can('access-notifications')
            @include('partials.navbar.notifications')
        @endcan

        {{-- Language switcher (ID/EN) — session-based, see routes/web.php's
             /lang/{locale} and App\Http\Middleware\SetLocale. Hardcoded here
             (rather than left to each page's @yield) so it shows on every
             admin screen without every view having to declare it. --}}
        <li class="nav-item d-flex align-items-center navbar-lang">
            <a class="nav-link {{ app()->getLocale() === 'id' ? 'is-active' : '' }}" href="{{ route('lang.switch', 'id') }}">ID</a>
            <a class="nav-link {{ app()->getLocale() === 'en' ? 'is-active' : '' }}" href="{{ route('lang.switch', 'en') }}">EN</a>
        </li>

        {{-- Custom right links --}}
        @yield('content_top_nav_right')

        {{-- Configured right links --}}
        @each('adminlte::partials.navbar.menu-item', $adminlte->menu('navbar-right'), 'item')

        {{-- User menu link --}}
        @if(Auth::user())
            @if(config('adminlte.usermenu_enabled'))
                @include('adminlte::partials.navbar.menu-item-dropdown-user-menu')
            @else
                @include('adminlte::partials.navbar.menu-item-logout-link')
            @endif
        @endif

        {{-- Right sidebar toggler link --}}
        @if($layoutHelper->isRightSidebarEnabled())
            @include('adminlte::partials.navbar.menu-item-right-sidebar-toggler')
        @endif
    </ul>

</nav>

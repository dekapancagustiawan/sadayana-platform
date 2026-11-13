{{-- main-sidebar-custom: AdminLTE menyisakan ruang untuk .sidebar-custom di bawah menu. --}}
<aside class="main-sidebar main-sidebar-custom {{ config('adminlte.classes_sidebar', 'sidebar-dark-primary elevation-4') }}">

    {{-- Sidebar brand logo --}}
    @if(config('adminlte.logo_img_xl'))
        @include('adminlte::partials.common.brand-logo-xl')
    @else
        @include('adminlte::partials.common.brand-logo-xs')
    @endif

    {{-- Sidebar menu --}}
    <div class="sidebar">
        <nav class="pt-2">
            <ul class="nav nav-pills nav-sidebar flex-column {{ config('adminlte.classes_sidebar_nav', '') }}"
                data-widget="treeview" role="menu"
                @if(config('adminlte.sidebar_nav_animation_speed') != 300)
                    data-animation-speed="{{ config('adminlte.sidebar_nav_animation_speed') }}"
                @endif
                @if(!config('adminlte.sidebar_nav_accordion'))
                    data-accordion="false"
                @endif>
                {{-- Configured sidebar links --}}
                @each('adminlte::partials.sidebar.menu-item', $adminlte->menu('sidebar'), 'item')
            </ul>
        </nav>
    </div>

    {{-- Versi aplikasi (APP_VERSION di .env) --}}
    <div class="sidebar-custom sidebar-version">
        <span class="sidebar-version-label">Versi</span>
        <span class="sidebar-version-number">{{ \Illuminate\Support\Str::start(config('app.version'), 'v') }}</span>
    </div>

</aside>

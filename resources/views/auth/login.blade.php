@extends('adminlte::auth.login')

{{-- Tampilan halaman login diatur di public/css/custom.css (bagian "Login"). --}}

@section('auth_header', 'Masuk ke akun Anda')

@section('auth_body')
    @if (session('status'))
        <div class="alert alert-success small" role="status">{{ session('status') }}</div>
    @endif

    <form id="login-form" action="{{ route('login.submit') }}" method="POST">
        @csrf

        {{-- Email atau nomor HP (FR-AUTH-001) --}}
        <div class="input-group mb-3">
            <input
                id="login"
                type="text"
                name="login"
                value="{{ old('login') }}"
                class="form-control @error('login') is-invalid @enderror"
                placeholder="Email atau nomor HP"
                autocomplete="username"
                autofocus
                required
            >
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-user"></span>
                </div>
            </div>
            @error('login')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        {{-- Password --}}
        <div class="input-group mb-3">
            <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                placeholder="Password"
                autocomplete="current-password"
                required
            >
            <div class="input-group-append">
                <button
                    type="button"
                    id="toggle-password"
                    class="input-group-text btn-password-toggle"
                    aria-controls="password"
                    aria-pressed="false"
                    aria-label="Tampilkan password"
                    title="Tampilkan password"
                >
                    <span class="fas fa-eye" aria-hidden="true"></span>
                </button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        {{-- Remember Me --}}
        <div class="row mb-3">
            <div class="col-8">
                <div class="icheck-primary">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Ingat saya</label>
                </div>
            </div>
            <div class="col-4 text-right">
                <a href="{{ route('password.request') }}" class="small">Lupa password?</a>
            </div>
        </div>

        @include('partials.recaptcha', ['action' => 'login'])
        @error(\App\Support\Captcha::FIELD)
            <div class="alert alert-danger py-2 small" role="alert">{{ $message }}</div>
        @enderror

        {{-- Submit --}}
        <div class="row">
            <div class="col-12">
                <button id="btn-login" type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt mr-1"></i> Sign In
                </button>
            </div>
        </div>
    </form>
@stop

@section('auth_footer')
    <div class="d-flex justify-content-between align-items-center w-100 text-muted" style="font-size:0.8rem;">
        <span>{{ config('app.name') }} &copy; {{ date('Y') }}</span>
        <a href="{{ route('public.home') }}" class="text-muted">&larr; Beranda</a>
    </div>
@stop

@push('js')
<script>
    // Tombol mata: tampilkan/sembunyikan isi field password.
    (function () {
        const input = document.getElementById('password');
        const toggle = document.getElementById('toggle-password');
        if (!input || !toggle) return;

        const icon = toggle.querySelector('span');

        toggle.addEventListener('click', function () {
            const show = input.type === 'password';
            const label = show ? 'Sembunyikan password' : 'Tampilkan password';

            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', label);
            toggle.title = label;
            input.focus();
        });

        // Jangan sampai password terkirim/tersimpan browser dalam mode teks.
        input.form && input.form.addEventListener('submit', function () {
            input.type = 'password';
        });
    })();
</script>
@endpush

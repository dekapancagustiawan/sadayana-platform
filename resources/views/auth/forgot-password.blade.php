@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('auth_header', 'Lupa password')

@section('auth_body')
    @if (session('status'))
        <div class="alert alert-success small" role="status">{{ session('status') }}</div>
    @endif

    <p class="text-muted small">
        Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat password baru.
        Akun tanpa email: hubungi pengurus untuk reset password.
    </p>

    <form id="forgot-password-form" action="{{ route('password.email') }}" method="POST">
        @csrf

        <div class="input-group mb-3">
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                placeholder="Email"
                autocomplete="email"
                autofocus
                required
            >
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-envelope"></span></div>
            </div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        @include('partials.recaptcha', ['action' => 'forgot_password'])
        @error(\App\Support\Captcha::FIELD)
            <div class="alert alert-danger py-2 small" role="alert">{{ $message }}</div>
        @enderror

        <button id="btn-send-reset" type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-paper-plane mr-1"></i> Kirim tautan reset
        </button>
    </form>
@stop

@section('auth_footer')
    <a href="{{ route('login') }}" class="small">&larr; Kembali ke halaman masuk</a>
@stop

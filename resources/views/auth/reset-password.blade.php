@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('auth_header', 'Buat password baru')

@section('auth_body')
    <form id="reset-password-form" action="{{ route('password.update') }}" method="POST">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="input-group mb-3">
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                class="form-control @error('email') is-invalid @enderror"
                placeholder="Email"
                autocomplete="email"
                required
            >
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-envelope"></span></div>
            </div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="input-group mb-3">
            <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                placeholder="Password baru (min. 8 karakter, huruf & angka)"
                autocomplete="new-password"
                required
            >
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-lock"></span></div>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="input-group mb-3">
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                class="form-control"
                placeholder="Ulangi password baru"
                autocomplete="new-password"
                required
            >
            <div class="input-group-append">
                <div class="input-group-text"><span class="fas fa-lock"></span></div>
            </div>
        </div>

        <button id="btn-reset-password" type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-key mr-1"></i> Simpan password baru
        </button>
    </form>
@stop

@section('auth_footer')
    <a href="{{ route('login') }}" class="small">&larr; Kembali ke halaman masuk</a>
@stop

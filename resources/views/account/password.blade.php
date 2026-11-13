@extends('adminlte::page')

@section('title', 'Ganti Password')

@section('content_header')
    <h1 class="m-0">
        <i class="fas fa-key mr-2 text-primary"></i>
        Ganti Password
    </h1>
@stop

@section('content')

    @if ($mustChange)
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Anda masuk dengan <strong>password sementara</strong>. Ganti password terlebih dahulu untuk melanjutkan.
        </div>
    @endif

    <div class="row">
        <div class="col-md-8 col-lg-6">
            <div class="card card-outline card-primary">
                <form id="change-password-form" action="{{ route('account.password.update') }}" method="POST" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="card-body">
                        <div class="form-group">
                            <label for="current_password">Password saat ini <span class="text-danger">*</span></label>
                            <input
                                id="current_password"
                                type="password"
                                name="current_password"
                                class="form-control @error('current_password') is-invalid @enderror"
                                autocomplete="current-password"
                                required
                            >
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password">Password baru <span class="text-danger">*</span></label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                autocomplete="new-password"
                                required
                            >
                            <small class="form-text text-muted">Minimal 8 karakter, mengandung huruf dan angka.</small>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation">Ulangi password baru <span class="text-danger">*</span></label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                class="form-control"
                                autocomplete="new-password"
                                required
                            >
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-end">
                        <button id="btn-change-password" type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@stop

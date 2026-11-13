@extends('adminlte::page')

@section('title', 'Settings')

@php
    $activeTab = old('_tab', request('tab', 'general')) === 'mail' ? 'mail' : 'general';
@endphp

@section('content_header')
    <h1 class="m-0">
        <i class="fas fa-cogs mr-2 text-primary"></i>
        Settings
    </h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="card card-primary card-outline card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'general' ? 'active' : '' }}" data-toggle="pill" href="#tab-general" role="tab">
                        <i class="fas fa-sliders-h mr-1"></i> Umum
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'mail' ? 'active' : '' }}" data-toggle="pill" href="#tab-mail" role="tab">
                        <i class="fas fa-envelope mr-1"></i> Email
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content">

                {{-- ═══ Umum ═══════════════════════════════════════════════ --}}
                <div class="tab-pane fade {{ $activeTab === 'general' ? 'show active' : '' }}" id="tab-general" role="tabpanel">
                    <form action="{{ route('settings.general.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_tab" value="general">

                        <div class="row">
                            <div class="col-lg-6">
                                <h5 class="mb-3">Identitas</h5>

                                <div class="form-group">
                                    <label for="app_name">Nama Aplikasi <span class="text-danger">*</span></label>
                                    <input id="app_name" type="text" name="app_name" required
                                           value="{{ old('app_name', $settings['app_name']) }}"
                                           class="form-control @error('app_name') is-invalid @enderror">
                                    @error('app_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <small class="form-text text-muted">Dipakai di judul halaman, landing page, dan email.</small>
                                </div>

                                <div class="form-group">
                                    <label for="app_logo">Logo</label>
                                    @if ($settings['app_logo_url'])
                                        <div class="mb-2 p-2 bg-dark d-inline-block rounded">
                                            <img src="{{ $settings['app_logo_url'] }}" alt="Logo" style="max-height:48px;">
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input" id="remove_logo" name="remove_logo" value="1">
                                            <label class="custom-control-label font-weight-normal" for="remove_logo">Hapus logo</label>
                                        </div>
                                    @endif
                                    <input id="app_logo" type="file" name="app_logo" accept="image/png,image/jpeg,image/webp"
                                           class="form-control-file @error('app_logo') is-invalid @enderror">
                                    @error('app_logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <small class="form-text text-muted">PNG/JPG/WebP, maks. 1 MB.</small>
                                </div>

                                <h5 class="mt-4 mb-3">Kontak</h5>

                                <div class="form-group">
                                    <label for="contact_email">Email</label>
                                    <input id="contact_email" type="email" name="contact_email"
                                           value="{{ old('contact_email', $settings['contact_email']) }}"
                                           class="form-control @error('contact_email') is-invalid @enderror">
                                    @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <small class="form-text text-muted">Penerima pesan dari form kontak landing page.</small>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="contact_phone">Telepon</label>
                                        <input id="contact_phone" type="text" name="contact_phone"
                                               value="{{ old('contact_phone', $settings['contact_phone']) }}"
                                               class="form-control @error('contact_phone') is-invalid @enderror">
                                        @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="contact_whatsapp">WhatsApp</label>
                                        <input id="contact_whatsapp" type="text" name="contact_whatsapp" placeholder="6281234567890"
                                               value="{{ old('contact_whatsapp', $settings['contact_whatsapp']) }}"
                                               class="form-control @error('contact_whatsapp') is-invalid @enderror">
                                        @error('contact_whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="contact_address">Alamat</label>
                                    <textarea id="contact_address" name="contact_address" rows="2"
                                              class="form-control @error('contact_address') is-invalid @enderror">{{ old('contact_address', $settings['contact_address']) }}</textarea>
                                    @error('contact_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <h5 class="mb-3">Sosial Media</h5>

                                @foreach (['instagram' => 'fab fa-instagram', 'facebook' => 'fab fa-facebook', 'tiktok' => 'fab fa-tiktok', 'youtube' => 'fab fa-youtube'] as $network => $icon)
                                    @php($field = "social_{$network}")
                                    <div class="form-group">
                                        <label for="{{ $field }}"><i class="{{ $icon }} mr-1"></i> {{ ucfirst($network) }}</label>
                                        <input id="{{ $field }}" type="url" name="{{ $field }}" placeholder="https://"
                                               value="{{ old($field, $settings[$field]) }}"
                                               class="form-control @error($field) is-invalid @enderror">
                                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Simpan
                        </button>
                    </form>
                </div>

                {{-- ═══ Email ══════════════════════════════════════════════ --}}
                <div class="tab-pane fade {{ $activeTab === 'mail' ? 'show active' : '' }}" id="tab-mail" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-7">
                            <form action="{{ route('settings.mail.update') }}" method="POST" id="mail-settings-form">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_tab" value="mail">

                                <div class="form-group">
                                    <label for="mail_mailer">Mailer <span class="text-danger">*</span></label>
                                    <select id="mail_mailer" name="mail_mailer" class="form-control @error('mail_mailer') is-invalid @enderror">
                                        @foreach ($mailers as $mailer)
                                            <option value="{{ $mailer }}" @selected(old('mail_mailer', $settings['mail_mailer']) === $mailer)>
                                                {{ $mailer === 'smtp' ? 'SMTP' : 'Log (tidak dikirim, ditulis ke storage/logs)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('mail_mailer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div id="smtp-fields">
                                    <div class="form-row">
                                        <div class="form-group col-md-8">
                                            <label for="mail_host">Host SMTP</label>
                                            <input id="mail_host" type="text" name="mail_host" placeholder="smtp.example.com"
                                                   value="{{ old('mail_host', $settings['mail_host']) }}"
                                                   class="form-control @error('mail_host') is-invalid @enderror">
                                            @error('mail_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="mail_port">Port</label>
                                            <input id="mail_port" type="number" name="mail_port" placeholder="587"
                                                   value="{{ old('mail_port', $settings['mail_port']) }}"
                                                   class="form-control @error('mail_port') is-invalid @enderror">
                                            @error('mail_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="mail_scheme">Enkripsi</label>
                                        <select id="mail_scheme" name="mail_scheme" class="form-control @error('mail_scheme') is-invalid @enderror">
                                            <option value="smtp" @selected(old('mail_scheme', $settings['mail_scheme']) === 'smtp')>STARTTLS / tanpa enkripsi (port 587 atau 25)</option>
                                            <option value="smtps" @selected(old('mail_scheme', $settings['mail_scheme']) === 'smtps')>SSL/TLS (port 465)</option>
                                        </select>
                                        @error('mail_scheme')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="mail_username">Username</label>
                                            <input id="mail_username" type="text" name="mail_username" autocomplete="off"
                                                   value="{{ old('mail_username', $settings['mail_username']) }}"
                                                   class="form-control @error('mail_username') is-invalid @enderror">
                                            @error('mail_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="mail_password">Password</label>
                                            <input id="mail_password" type="password" name="mail_password" autocomplete="new-password"
                                                   placeholder="{{ $settings['mail_password_set'] ? '•••••••• (tersimpan)' : '' }}"
                                                   class="form-control @error('mail_password') is-invalid @enderror">
                                            @error('mail_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small class="form-text text-muted">Kosongkan untuk mempertahankan password lama. Disimpan terenkripsi.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="mail_from_address">Alamat Pengirim <span class="text-danger">*</span></label>
                                        <input id="mail_from_address" type="email" name="mail_from_address" required
                                               value="{{ old('mail_from_address', $settings['mail_from_address']) }}"
                                               class="form-control @error('mail_from_address') is-invalid @enderror">
                                        @error('mail_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="mail_from_name">Nama Pengirim <span class="text-danger">*</span></label>
                                        <input id="mail_from_name" type="text" name="mail_from_name" required
                                               value="{{ old('mail_from_name', $settings['mail_from_name']) }}"
                                               class="form-control @error('mail_from_name') is-invalid @enderror">
                                        @error('mail_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i> Simpan
                                </button>
                            </form>
                        </div>

                        <div class="col-lg-5">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title mb-2"><i class="fas fa-paper-plane mr-1"></i> Kirim Email Test</h5>
                                    <p class="text-muted small mb-3">Simpan pengaturan dulu, lalu kirim email test untuk memastikan konfigurasinya benar. Hasilnya tercatat di Email Log.</p>
                                    <form action="{{ route('settings.mail.test') }}" method="POST">
                                        @csrf
                                        <div class="input-group">
                                            <input type="email" name="test_recipient" required
                                                   value="{{ old('test_recipient', auth()->user()->email) }}"
                                                   class="form-control @error('test_recipient') is-invalid @enderror">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-outline-primary">Kirim</button>
                                            </div>
                                        </div>
                                        @error('test_recipient')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </form>
                                    <a href="{{ route('email-logs.index') }}" class="d-inline-block mt-3 small">
                                        Lihat Email Log <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        (function () {
            var mailer = document.getElementById('mail_mailer');
            var smtpFields = document.getElementById('smtp-fields');

            function toggleSmtp() {
                smtpFields.style.display = mailer.value === 'smtp' ? '' : 'none';
            }

            mailer.addEventListener('change', toggleSmtp);
            toggleSmtp();
        })();
    </script>
@stop

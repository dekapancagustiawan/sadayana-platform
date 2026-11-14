@extends('adminlte::page')

@section('title', $resident->full_name)

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-user mr-2 text-primary"></i>{{ $resident->full_name }}
            <span class="badge badge-{{ $resident->status->badge() }} align-middle" style="font-size:.9rem">{{ $resident->status->label() }}</span>
        </h1>
        <div class="mt-2 mt-sm-0">
            <a href="{{ route('residents.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Daftar penghuni</a>
            @can('residents.manage')
                <a href="{{ route('residents.edit', $resident) }}" class="btn btn-warning btn-sm"><i class="fas fa-edit mr-1"></i> Ubah</a>
            @endcan
        </div>
    </div>
@stop

@section('content')
    @include('partials.flash')

    @if ($temporary = session('temporary_password'))
        <div class="alert alert-info">
            <h5 class="mb-2"><i class="fas fa-key mr-1"></i> Akun warga dibuat</h5>
            Serahkan data masuk berikut ke warga. <strong>Password ini hanya ditampilkan sekali</strong> dan wajib diganti saat pertama kali masuk.
            <div class="mt-2 p-2 bg-white text-dark rounded" style="font-family:monospace">
                Email/No. HP: {{ $temporary['login'] }}<br>
                Password sementara: <strong>{{ $temporary['password'] }}</strong>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Data penghuni</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">No. HP</dt><dd class="col-7">{{ $resident->displayPhone() ?? '—' }}</dd>
                        <dt class="col-5">Email</dt><dd class="col-7">{{ $resident->email ?? '—' }}</dd>
                        <dt class="col-5">No. warga</dt><dd class="col-7">{{ $resident->resident_number ?? '—' }}</dd>
                        <dt class="col-5">Tanggal lahir</dt><dd class="col-7">{{ $resident->birth_date?->format('d/m/Y') ?? '—' }}</dd>
                        <dt class="col-5">NIK</dt>
                        <dd class="col-7">
                            @if ($canViewIdentity)
                                {{ $resident->identity_number ?? '—' }}
                            @else
                                <span class="text-muted"><i class="fas fa-lock"></i> dibatasi</span>
                            @endif
                        </dd>
                        @if ($resident->notes)
                            <dt class="col-5">Catatan</dt><dd class="col-7" style="white-space:pre-line">{{ $resident->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card card-outline card-info">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-user-circle mr-1"></i> Akun aplikasi</h3></div>
                <div class="card-body">
                    @forelse ($resident->users as $user)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>{{ $user->name }}</strong>
                                <div class="small text-muted">{{ $user->email ?? \App\Support\Phone::display($user->phone) }} · {{ $user->status->value === 'active' ? 'aktif' : 'nonaktif' }}</div>
                            </div>
                            @can('residents.accounts')
                                <form action="{{ route('residents.account.destroy', [$resident, $user]) }}" method="POST"
                                      onsubmit="return confirm('Putuskan hubungan akun {{ addslashes($user->name) }} dari penghuni ini?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger" title="Putuskan"><i class="fas fa-unlink"></i></button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-muted mb-2">Penghuni ini belum punya akun untuk masuk ke aplikasi.</p>
                    @endforelse

                    @can('residents.accounts')
                        <hr>
                        @if ($resident->users->isEmpty())
                            <form action="{{ route('residents.account.store', $resident) }}" method="POST" class="mb-3">
                                @csrf
                                <button class="btn btn-sm btn-info btn-block" @disabled(! $resident->phone && ! $resident->email)>
                                    <i class="fas fa-user-plus mr-1"></i> Buat akun warga
                                </button>
                                @if (! $resident->phone && ! $resident->email)
                                    <small class="text-muted">Isi nomor HP atau email penghuni dulu.</small>
                                @endif
                            </form>
                        @endif
                        <form action="{{ route('residents.account.link', $resident) }}" method="POST">
                            @csrf
                            <label class="small mb-1" for="link-login">Hubungkan akun yang sudah ada</label>
                            <div class="input-group input-group-sm">
                                <input id="link-login" name="login" type="text" class="form-control" placeholder="email atau nomor HP akun" value="{{ old('login') }}">
                                <div class="input-group-append"><button class="btn btn-outline-info"><i class="fas fa-link"></i></button></div>
                            </div>
                        </form>
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1"></i> Riwayat hunian</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light"><tr><th>Rumah</th><th>Hubungan</th><th>Periode</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($resident->occupancies as $occupancy)
                                <tr>
                                    <td><a href="{{ route('houses.show', $occupancy->house) }}">{{ $occupancy->house->house_code }}</a></td>
                                    <td>{{ $occupancy->relationship_type->label() }}</td>
                                    <td class="text-nowrap">{{ $occupancy->start_date->format('d/m/Y') }} – {{ $occupancy->end_date?->format('d/m/Y') ?? 'sekarang' }}</td>
                                    <td>
                                        @if ($occupancy->isActive())
                                            <span class="badge badge-success">menghuni</span>
                                        @else
                                            <span class="badge badge-secondary">keluar</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum tercatat menghuni rumah mana pun. Catat dari halaman rumah.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

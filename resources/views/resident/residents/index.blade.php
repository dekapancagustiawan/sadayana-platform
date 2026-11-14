@extends('adminlte::page')

@section('title', 'Penghuni')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-users mr-2 text-primary"></i>Penghuni</h1>
        @can('residents.manage')
            <a href="{{ route('residents.create') }}" class="btn btn-primary btn-sm" id="btn-create-resident">
                <i class="fas fa-plus mr-1"></i> Tambah Penghuni
            </a>
        @endcan
    </div>
@stop

@section('content')
    @include('partials.flash')

    <div class="card card-outline card-primary">
        <div class="card-body pb-1">
            <form method="GET" action="{{ route('residents.index') }}" class="form-row">
                <div class="col-md-7 mb-2">
                    <div class="input-group">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari nama, email, atau nomor HP…">
                        <div class="input-group-append"><button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button></div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 mb-2">
                    <a href="{{ route('residents.index') }}" class="btn btn-secondary btn-block" title="Hapus filter"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar penghuni <span class="badge badge-secondary ml-1">{{ $residents->total() }}</span></h3>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr><th>Nama</th><th>No. HP</th><th>Rumah</th><th>Status</th><th>Akun</th></tr>
                </thead>
                <tbody>
                    @forelse ($residents as $resident)
                        <tr>
                            <td><a href="{{ route('residents.show', $resident) }}">{{ $resident->full_name }}</a></td>
                            <td class="text-nowrap">{{ $resident->displayPhone() ?? '—' }}</td>
                            <td>
                                @forelse ($resident->activeOccupancies as $occupancy)
                                    <a href="{{ route('houses.show', $occupancy->house) }}" class="badge badge-light">{{ $occupancy->house->house_code }}</a>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td><span class="badge badge-{{ $resident->status->badge() }}">{{ $resident->status->label() }}</span></td>
                            <td>
                                @if ($resident->users->isNotEmpty())
                                    <span class="badge badge-success"><i class="fas fa-user-check"></i></span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada penghuni yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($residents->hasPages())
            <div class="card-footer">{{ $residents->links('partials.pagination') }}</div>
        @endif
    </div>
@stop

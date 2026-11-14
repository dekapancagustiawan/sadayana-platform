@extends('adminlte::page')

@section('title', 'Rumah')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-house-user mr-2 text-primary"></i>Rumah</h1>
        <div class="mt-2 mt-sm-0">
            @can('residents.import')
                <a href="{{ route('houses.import') }}" class="btn btn-outline-primary btn-sm" id="btn-import-houses">
                    <i class="fas fa-file-import mr-1"></i> Import CSV
                </a>
            @endcan
            @can('houses.manage')
                <a href="{{ route('houses.create', ['block' => request('block')]) }}" class="btn btn-primary btn-sm" id="btn-create-house">
                    <i class="fas fa-plus mr-1"></i> Tambah Rumah
                </a>
            @endcan
        </div>
    </div>
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        @foreach ($occupancyStatuses as $status)
            <div class="col-6 col-md-3">
                <a href="{{ route('houses.index', ['occupancy' => $status->value]) }}" class="small-box bg-white text-dark d-block">
                    <div class="inner">
                        <h3>{{ $totals[$status->value] ?? 0 }}</h3>
                        <p class="mb-0"><span class="badge badge-{{ $status->badge() }}">{{ $status->label() }}</span></p>
                    </div>
                </a>
            </div>
        @endforeach
        <div class="col-6 col-md-3">
            <a href="{{ route('houses.index') }}" class="small-box bg-white text-dark d-block">
                <div class="inner">
                    <h3>{{ $totals->sum() }}</h3>
                    <p class="mb-0"><span class="badge badge-primary">Total rumah</span></p>
                </div>
            </a>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-body pb-1">
            <form method="GET" action="{{ route('houses.index') }}" class="form-row">
                <div class="col-md-5 mb-2">
                    <div class="input-group">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari kode rumah (J-12) atau nama penghuni…">
                        <div class="input-group-append"><button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button></div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="block" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua blok</option>
                        @foreach ($blocks as $block)
                            <option value="{{ $block->id }}" @selected(request('block') === $block->id)>{{ $block->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="occupancy" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua status hunian</option>
                        @foreach ($occupancyStatuses as $status)
                            <option value="{{ $status->value }}" @selected(request('occupancy') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 mb-2">
                    <a href="{{ route('houses.index') }}" class="btn btn-secondary btn-block" title="Hapus filter"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Rumah</th>
                        <th>Penghuni (kontak utama)</th>
                        <th>No. HP</th>
                        <th>Status hunian</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($houses as $house)
                        @php($primary = $house->primaryResident())
                        <tr @class(['text-muted' => ! $house->is_active])>
                            <td class="font-weight-bold text-nowrap">
                                <a href="{{ route('houses.show', $house) }}">{{ $house->house_code }}</a>
                                @unless ($house->is_active)
                                    <span class="badge badge-light">nonaktif</span>
                                @endunless
                            </td>
                            <td>
                                {{ $primary?->full_name ?? '—' }}
                                @if ($house->activeOccupancies->count() > 1)
                                    <span class="badge badge-light">+{{ $house->activeOccupancies->count() - 1 }}</span>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $primary?->displayPhone() ?? '—' }}</td>
                            <td><span class="badge badge-{{ $house->occupancy_status->badge() }}">{{ $house->occupancy_status->label() }}</span></td>
                            <td class="text-right">
                                <a href="{{ route('houses.show', $house) }}" class="btn btn-xs btn-outline-primary">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada rumah yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($houses->hasPages())
            <div class="card-footer">{{ $houses->links('partials.pagination') }}</div>
        @endif
    </div>
@stop

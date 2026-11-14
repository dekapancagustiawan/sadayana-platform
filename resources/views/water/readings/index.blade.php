@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Pembacaan Meter')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-tachometer-alt mr-2 text-primary"></i>Pembacaan Meter</h1>
@stop

@section('content')
    @include('partials.flash')

    @if ($pendingCount > 0 || $pendingCorrections->isNotEmpty())
        <div class="callout callout-warning">
            <h5 class="mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> Perlu tindakan</h5>
            @if ($pendingCount > 0)
                <div><a href="{{ route('meter-readings.index', ['period' => $period->format('Y-m'), 'status' => 'pending']) }}">{{ $pendingCount }} pembacaan</a> janggal menunggu pemeriksaan.</div>
            @endif
            @if ($pendingCorrections->isNotEmpty())
                <div>{{ $pendingCorrections->count() }} pengajuan koreksi menunggu persetujuan (lihat di bawah).</div>
            @endif
        </div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <form method="GET" action="{{ route('meter-readings.index') }}" class="card">
                <div class="card-body">
                    <div class="form-group">
                        <label for="period" class="small">Bulan pemakaian</label>
                        <input id="period" type="month" name="period" value="{{ $period->format('Y-m') }}" class="form-control" onchange="this.form.submit()">
                    </div>
                    <div class="form-group mb-0">
                        <label for="status" class="small">Status</label>
                        <select id="status" name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">Semua</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-md-8">
            <div class="row">
                <div class="col-sm-4">
                    <div class="small-box bg-white"><div class="inner"><h3>{{ $summary->houses }}</h3><p class="mb-0">Rumah dihitung</p></div></div>
                </div>
                <div class="col-sm-4">
                    <div class="small-box bg-white"><div class="inner"><h3>{{ Money::formatQuantity($summary->volume) }}</h3><p class="mb-0">Total m³</p></div></div>
                </div>
                <div class="col-sm-4">
                    <div class="small-box bg-white"><div class="inner"><h3 style="font-size:1.6rem">{{ Money::format($summary->amount) }}</h3><p class="mb-0">Tagihan air</p></div></div>
                </div>
            </div>
        </div>
    </div>

    @if ($pendingCorrections->isNotEmpty())
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title">Pengajuan koreksi</h3></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="thead-light"><tr><th>Rumah</th><th>Bulan</th><th>Angka lama → baru</th><th>Alasan</th><th>Diajukan</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pendingCorrections as $correction)
                            <tr>
                                <td><a href="{{ route('meter-readings.show', $correction->reading) }}">{{ $correction->reading->house->house_code }}</a></td>
                                <td>{{ $correction->reading->reading_period->translatedFormat('M Y') }}</td>
                                <td class="text-nowrap">{{ Money::formatQuantity($correction->old_value) }} → <strong>{{ Money::formatQuantity($correction->new_value) }}</strong></td>
                                <td>{{ $correction->reason }}</td>
                                <td class="small text-muted">{{ $correction->requester?->name }}<br>{{ $correction->created_at->format('d/m H:i') }}</td>
                                <td class="text-right text-nowrap">
                                    @can('meter-readings.validate')
                                        <form action="{{ route('reading-corrections.approve', $correction) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-xs btn-success">Setujui</button>
                                        </form>
                                        <a href="{{ route('meter-readings.show', $correction->reading) }}" class="btn btn-xs btn-outline-secondary">Detail</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">{{ $period->translatedFormat('F Y') }} <span class="badge badge-secondary ml-1">{{ $readings->count() }}</span></h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr><th>Rumah</th><th class="text-right">Angka</th><th class="text-right">Pakai (m³)</th><th class="text-right">Tagihan air</th><th>Status</th><th>Petugas</th></tr>
                </thead>
                <tbody>
                    @forelse ($readings as $reading)
                        <tr>
                            <td><a href="{{ route('meter-readings.show', $reading) }}" class="font-weight-bold">{{ $reading->house->house_code }}</a></td>
                            <td class="text-right">{{ Money::formatQuantity($reading->reading_value) }}</td>
                            <td class="text-right">{{ $reading->usage ? Money::formatQuantity($reading->usage->usage_volume) : '—' }}</td>
                            <td class="text-right">{{ $reading->usage ? Money::format($reading->usage->calculated_amount) : '—' }}</td>
                            <td>
                                <span class="badge badge-{{ $reading->status->badge() }}">{{ $reading->status->label() }}</span>
                                @foreach ($reading->anomalyList() as $anomaly)
                                    <span class="badge badge-light" title="{{ $anomaly->label() }}"><i class="fas fa-exclamation"></i> {{ $anomaly->label() }}</span>
                                @endforeach
                            </td>
                            <td class="small text-muted">{{ $reading->recorder?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pembacaan untuk bulan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

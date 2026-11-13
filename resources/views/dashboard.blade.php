@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-tachometer-alt mr-2 text-primary"></i>
            Dashboard
        </h1>
        <small class="text-muted">
            <i class="fas fa-clock mr-1"></i>{{ now()->format('d M Y, H:i') }}
        </small>
    </div>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="callout callout-info">
        <h5 class="mb-1">Selamat datang, {{ auth()->user()->name }}.</h5>
        <p class="mb-0 text-muted">
            Role: {{ auth()->user()->getRoleNames()->implode(', ') ?: '—' }}
        </p>
    </div>

    @if (count($stats))
        <div class="row">
            @foreach ($stats as $stat)
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-{{ $stat['color'] }}">
                        <div class="inner">
                            <h3>{{ number_format($stat['value']) }}</h3>
                            <p>{{ $stat['label'] }}</p>
                        </div>
                        <div class="icon"><i class="{{ $stat['icon'] }}"></i></div>
                        @if ($stat['url'])
                            <a href="{{ $stat['url'] }}" class="small-box-footer">
                                Lihat <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        @else
                            <span class="small-box-footer">&nbsp;</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @can('access-audit-logs')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clipboard-list mr-1"></i> Aktivitas Terbaru</h3>
                <div class="card-tools">
                    <a href="{{ route('audit-logs.index') }}" class="btn btn-tool">Semua log <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Aksi</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activities as $log)
                            <tr>
                                <td class="text-muted text-nowrap" title="{{ $log->created_at?->format('d M Y H:i:s') }}">
                                    {{ $log->created_at?->diffForHumans() }}
                                </td>
                                <td>{{ $log->user->name ?? 'System' }}</td>
                                <td><code class="text-primary">{{ $log->action }}</code></td>
                                <td class="text-muted">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Belum ada aktivitas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endcan
@stop

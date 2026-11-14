@extends('adminlte::page')

@use('App\Support\Money')

@php
    $total = $houses->count();
    $percent = $total ? round($done / $total * 100) : 0;
@endphp

@section('title', 'Catat Meter')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-camera mr-2 text-primary"></i>Catat Meter</h1>
@stop

@section('content')
    @include('partials.flash')

    <form method="GET" action="{{ route('meter-recording.index') }}" class="mb-3">
        <div class="form-row">
            <div class="col-6 col-md-3">
                <label for="period" class="small mb-1">Bulan pemakaian</label>
                <input id="period" type="month" name="period" value="{{ $period->format('Y-m') }}" max="{{ now()->format('Y-m') }}"
                       class="form-control" onchange="this.form.submit()">
            </div>
            <div class="col-6 col-md-4">
                <label for="search" class="small mb-1">Cari</label>
                <input id="search" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="J-12 / nama">
            </div>
        </div>
    </form>

    @if (! $hasAssignments)
        <div class="callout callout-warning">
            <h5>Belum ada rumah yang ditugaskan</h5>
            <p class="mb-0">Minta Ketua RT menambahkan penugasan <em>pencatatan meter air</em> untuk akun Anda.</p>
        </div>
    @else
        <div class="card">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span><strong>{{ $period->translatedFormat('F Y') }}</strong></span>
                    <span>{{ $done }} / {{ $total }} rumah tercatat</span>
                </div>
                <div class="progress progress-sm"><div class="progress-bar bg-success" style="width: {{ $percent }}%"></div></div>
            </div>
        </div>

        <div class="list-group mb-4">
            @forelse ($houses as $house)
                @php
                    $reading = $readings->get($house->id);
                    $primary = $house->primaryResident();
                @endphp
                <a href="{{ route('meter-recording.create', ['house' => $house, 'period' => $period->format('Y-m')]) }}"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                    <span>
                        <span class="font-weight-bold" style="font-size:1.1rem">{{ $house->house_code }}</span>
                        <span class="d-block small text-muted">{{ $primary?->full_name ?? 'Belum ada nama penghuni' }}</span>
                    </span>
                    @if (! $reading)
                        <span class="badge badge-light border">Belum dicatat <i class="fas fa-chevron-right ml-1"></i></span>
                    @elseif ($reading->status->value === 'pending')
                        <span class="badge badge-warning">{{ Money::formatQuantity($reading->reading_value) }} · diperiksa</span>
                    @else
                        <span class="badge badge-success"><i class="fas fa-check mr-1"></i>{{ Money::formatQuantity($reading->reading_value) }}</span>
                    @endif
                </a>
            @empty
                <div class="list-group-item text-center text-muted py-4">Tidak ada rumah yang cocok.</div>
            @endforelse
        </div>
    @endif
@stop

@extends('adminlte::page')

@section('title', 'Rumah Saya')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-home mr-2 text-primary"></i>Rumah Saya</h1>
@stop

@section('content')
    @include('partials.flash')

    @forelse ($houses as $house)
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">{{ $house->house_code }}</h3>
                <div class="card-tools"><span class="badge badge-{{ $house->occupancy_status->badge() }}">{{ $house->occupancy_status->label() }}</span></div>
            </div>
            <div class="card-body">
                <p class="mb-2 text-muted">{{ $house->block->label() }}{{ $house->address ? ' · '.$house->address : '' }}</p>
                <h6 class="text-uppercase text-muted small mb-2">Penghuni terdaftar</h6>
                <ul class="list-unstyled mb-0">
                    @foreach ($house->activeOccupancies as $occupancy)
                        <li class="mb-1">
                            <i class="fas fa-user text-muted mr-1"></i> {{ $occupancy->resident->full_name }}
                            <span class="text-muted small">· {{ $occupancy->relationship_type->label() }}</span>
                            @if ($occupancy->is_primary_contact) <span class="badge badge-info">kontak utama</span> @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            @php($usages = $waterUsages[$house->id] ?? collect())
            @if ($usages->isNotEmpty())
                <div class="card-body border-top pt-3">
                    <h6 class="text-uppercase text-muted small mb-2"><i class="fas fa-faucet mr-1"></i> Pemakaian air</h6>
                    <div class="table-responsive">
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>Bulan</th><th class="text-right">Meter</th><th class="text-right">Pakai</th><th class="text-right">Tagihan</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($usages as $usage)
                                    <tr>
                                        <td>{{ $usage->usage_period->translatedFormat('M Y') }}</td>
                                        <td class="text-right text-nowrap">{{ \App\Support\Money::formatQuantity($usage->previous_value) }} → {{ \App\Support\Money::formatQuantity($usage->current_value) }}</td>
                                        <td class="text-right text-nowrap">{{ \App\Support\Money::formatQuantity($usage->usage_volume) }} m³</td>
                                        <td class="text-right text-nowrap">{{ \App\Support\Money::format($usage->calculated_amount) }}</td>
                                        <td class="text-right">
                                            @if ($photo = $photos[$usage->current_reading_id] ?? null)
                                                <a href="{{ route('files.show', $photo) }}" target="_blank" rel="noopener" title="Foto meter"><i class="fas fa-image"></i></a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            <div class="card-footer small text-muted">
                Tagihan dan pembayaran rumah ini akan tampil di sini setelah modulnya aktif.
            </div>
        </div>
    @empty
        <div class="callout callout-info">
            <h5>Akun Anda belum terhubung ke rumah</h5>
            <p class="mb-0">Hubungi pengurus untuk menghubungkan akun ini dengan data penghuni rumah Anda.</p>
        </div>
    @endforelse
@stop

@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Tarif Air')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-tags mr-2 text-primary"></i>Tarif Air</h1>
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        <div class="col-lg-7">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title">Tarif yang berlaku sekarang</h3></div>
                <div class="card-body">
                    @if ($current)
                        <div class="row text-center">
                            <div class="col-4"><div class="text-muted small">Abodemen / bulan</div><div class="h4 mb-0">{{ Money::format($current->base_fee) }}</div></div>
                            <div class="col-4"><div class="text-muted small">Per m³</div><div class="h4 mb-0">{{ Money::format($current->rate_per_unit) }}</div></div>
                            <div class="col-4"><div class="text-muted small">Minimum</div><div class="h4 mb-0">{{ $current->minimum_charge ? Money::format($current->minimum_charge) : '—' }}</div></div>
                        </div>
                        <p class="small text-muted text-center mb-0 mt-3">Berlaku sejak {{ $current->effective_from->format('d/m/Y') }}. Tagihan air = abodemen + tarif × pemakaian.</p>
                    @else
                        <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle mr-1"></i> Tarif air belum diatur. Petugas belum bisa mencatat meter sampai tarif diisi.</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Riwayat tarif</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light"><tr><th>Berlaku</th><th class="text-right">Abodemen</th><th class="text-right">Per m³</th><th class="text-right">Minimum</th><th>Catatan</th></tr></thead>
                        <tbody>
                            @forelse ($periods as $period)
                                <tr>
                                    <td class="text-nowrap">{{ $period->effective_from->format('d/m/Y') }} – {{ $period->effective_until?->format('d/m/Y') ?? 'sekarang' }}</td>
                                    <td class="text-right">{{ Money::format($period->base_fee) }}</td>
                                    <td class="text-right">{{ Money::format($period->rate_per_unit) }}</td>
                                    <td class="text-right">{{ $period->minimum_charge ? Money::format($period->minimum_charge) : '—' }}</td>
                                    <td class="small">{{ $period->notes }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada tarif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @can('water-tariffs.manage')
            <div class="col-lg-5">
                <div class="card card-outline card-success">
                    <div class="card-header"><h3 class="card-title">{{ $current ? 'Ubah tarif (periode baru)' : 'Atur tarif' }}</h3></div>
                    <form action="{{ route('water-tariffs.periods.store') }}" method="POST" novalidate>
                        @csrf
                        <div class="card-body">
                            <p class="small text-muted">Tarif lama tidak diubah: tarif baru berlaku mulai tanggal yang dipilih, dan tagihan yang sudah dihitung tetap memakai tarif lamanya.</p>
                            <div class="form-group">
                                <label for="effective_from">Berlaku mulai</label>
                                <input id="effective_from" name="effective_from" type="date" required
                                       value="{{ old('effective_from', now()->startOfMonth()->addMonthNoOverflow()->toDateString()) }}"
                                       class="form-control @error('effective_from') is-invalid @enderror">
                                @error('effective_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-row">
                                <div class="form-group col-6">
                                    <label for="base_fee">Abodemen (Rp)</label>
                                    <input id="base_fee" name="base_fee" type="number" min="0" step="1" required value="{{ old('base_fee', $current ? (int) $current->base_fee : 5000) }}"
                                           class="form-control @error('base_fee') is-invalid @enderror">
                                    @error('base_fee') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-group col-6">
                                    <label for="rate_per_unit">Tarif per m³ (Rp)</label>
                                    <input id="rate_per_unit" name="rate_per_unit" type="number" min="0" step="1" required value="{{ old('rate_per_unit', $current ? (int) $current->rate_per_unit : 5000) }}"
                                           class="form-control @error('rate_per_unit') is-invalid @enderror">
                                    @error('rate_per_unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="minimum_charge">Tagihan minimum (Rp, opsional)</label>
                                <input id="minimum_charge" name="minimum_charge" type="number" min="0" step="1" value="{{ old('minimum_charge') }}"
                                       class="form-control @error('minimum_charge') is-invalid @enderror">
                            </div>
                            <div class="form-group mb-0">
                                <label for="notes">Catatan</label>
                                <input id="notes" name="notes" type="text" maxlength="500" value="{{ old('notes') }}" class="form-control" placeholder="mis. keputusan rapat warga 10/2026">
                            </div>
                        </div>
                        <div class="card-footer text-right"><button class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan tarif</button></div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@stop

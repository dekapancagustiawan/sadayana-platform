@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Meter '.$house->house_code)

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-tachometer-alt mr-2 text-primary"></i>{{ $house->house_code }} · {{ $reading->reading_period->translatedFormat('F Y') }}
            <span class="badge badge-{{ $reading->status->badge() }} align-middle" style="font-size:.9rem">{{ $reading->status->label() }}</span>
        </h1>
        <a href="{{ route('meter-readings.index', ['period' => $reading->reading_period->format('Y-m')]) }}" class="btn btn-secondary btn-sm mt-2 mt-sm-0"><i class="fas fa-arrow-left mr-1"></i> Daftar</a>
    </div>
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body text-center">
                    @if ($reading->photo_file_id)
                        <a href="{{ route('files.show', $reading->photo_file_id) }}" target="_blank" rel="noopener">
                            <img src="{{ route('files.show', $reading->photo_file_id) }}" alt="Foto meter {{ $house->house_code }}" class="img-fluid rounded" style="max-height:360px">
                        </a>
                    @else
                        <p class="text-muted my-5">Tanpa foto.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Angka sebelumnya</dt>
                        <dd class="col-7">{{ Money::formatQuantity($baseline['value']) }}
                            <span class="text-muted small">({{ $baseline['reading'] ? $baseline['reading']->reading_period->translatedFormat('F Y') : 'angka awal meter' }})</span></dd>
                        <dt class="col-5">Angka bulan ini</dt>
                        <dd class="col-7"><strong style="font-size:1.2rem">{{ Money::formatQuantity($reading->reading_value) }}</strong></dd>
                        @if ($baseline['carried']->isPositive())
                            <dt class="col-5">Sisa meter lama</dt><dd class="col-7">{{ Money::formatQuantity($baseline['carried']) }} m³</dd>
                        @endif
                        @if ($reading->usage)
                            <dt class="col-5">Pemakaian</dt><dd class="col-7">{{ Money::formatQuantity($reading->usage->usage_volume) }} m³</dd>
                            <dt class="col-5">Tagihan air</dt>
                            <dd class="col-7">
                                <strong>{{ Money::format($reading->usage->calculated_amount) }}</strong>
                                <span class="text-muted small d-block">abodemen {{ Money::format($reading->usage->base_fee) }} + {{ Money::formatQuantity($reading->usage->usage_volume) }} m³ × {{ Money::format($reading->usage->rate_per_unit) }}</span>
                                @if ($reading->usage->isBilled()) <span class="badge badge-info">sudah ditagihkan</span> @endif
                            </dd>
                        @endif
                        @if ($reading->anomalyList())
                            <dt class="col-5">Kejanggalan</dt>
                            <dd class="col-7">@foreach ($reading->anomalyList() as $a) <span class="badge badge-warning">{{ $a->label() }}</span> @endforeach</dd>
                        @endif
                        <dt class="col-5">Dicatat</dt><dd class="col-7">{{ $reading->recorder?->name ?? '—' }} · {{ $reading->reading_date->format('d/m/Y H:i') }}</dd>
                        @if ($reading->notes)
                            <dt class="col-5">Catatan petugas</dt><dd class="col-7">{{ $reading->notes }}</dd>
                        @endif
                        @if ($reading->validator)
                            <dt class="col-5">Diperiksa</dt><dd class="col-7">{{ $reading->validator->name }} · {{ $reading->validated_at?->format('d/m/Y H:i') }}</dd>
                        @endif
                        @if ($reading->rejection_reason)
                            <dt class="col-5">Alasan ditolak</dt><dd class="col-7">{{ $reading->rejection_reason }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @if ($reading->status->value === 'pending')
                @can('meter-readings.validate')
                    <div class="card card-outline card-warning">
                        <div class="card-header"><h3 class="card-title">Periksa pembacaan</h3></div>
                        <div class="card-body">
                            <form action="{{ route('meter-readings.validate', $reading) }}" method="POST" class="mb-3">
                                @csrf
                                <div class="form-row">
                                    <div class="form-group col-sm-5">
                                        <label class="small" for="corrected_value">Angka yang benar (bila perlu dibetulkan)</label>
                                        <input id="corrected_value" name="corrected_value" type="number" step="0.001" min="0" value="{{ old('corrected_value') }}"
                                               class="form-control" placeholder="{{ Money::formatQuantity($reading->reading_value) }}">
                                    </div>
                                    <div class="form-group col-sm-7">
                                        <label class="small" for="notes">Catatan</label>
                                        <input id="notes" name="notes" type="text" maxlength="500" value="{{ old('notes') }}" class="form-control" placeholder="wajib bila angka dibetulkan">
                                    </div>
                                </div>
                                <button class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i> Validasi</button>
                            </form>
                            <form action="{{ route('meter-readings.reject', $reading) }}" method="POST" class="form-inline">
                                @csrf
                                <input name="reason" type="text" maxlength="500" required class="form-control form-control-sm mr-2 mb-2 flex-grow-1" placeholder="Alasan menolak (petugas mencatat ulang)">
                                <button class="btn btn-outline-danger btn-sm mb-2"><i class="fas fa-times mr-1"></i> Tolak</button>
                            </form>
                        </div>
                    </div>
                @endcan
            @elseif ($reading->status->value === 'validated')
                @can('meter-readings.correct')
                    <div class="card collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title">Ajukan koreksi angka</h3>
                            <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
                        </div>
                        <form action="{{ route('meter-readings.corrections.store', $reading) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-sm-4">
                                        <label class="small">Angka yang benar</label>
                                        <input name="new_value" type="number" step="0.001" min="0" required class="form-control">
                                    </div>
                                    <div class="form-group col-sm-8">
                                        <label class="small">Alasan</label>
                                        <input name="reason" type="text" maxlength="500" required class="form-control">
                                    </div>
                                </div>
                                <input name="photo" type="file" accept="image/*" class="form-control-file">
                            </div>
                            <div class="card-footer text-right"><button class="btn btn-warning btn-sm">Ajukan</button></div>
                        </form>
                    </div>
                @endcan
            @endif

            @if ($reading->corrections->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Riwayat koreksi</h3></div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="thead-light"><tr><th>Angka</th><th>Alasan</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($reading->corrections as $correction)
                                    <tr>
                                        <td class="text-nowrap">{{ Money::formatQuantity($correction->old_value) }} → {{ Money::formatQuantity($correction->new_value) }}</td>
                                        <td>{{ $correction->reason }}
                                            <div class="small text-muted">{{ $correction->requester?->name }} · {{ $correction->created_at->format('d/m/Y H:i') }}</div>
                                            @if ($correction->decision_notes) <div class="small">Keputusan: {{ $correction->decision_notes }}</div> @endif
                                        </td>
                                        <td>{{ $correction->status->label() }}
                                            @if ($correction->decider) <div class="small text-muted">{{ $correction->decider->name }}</div> @endif
                                        </td>
                                        <td class="text-right text-nowrap">
                                            @if ($correction->status->value === 'pending')
                                                @can('meter-readings.validate')
                                                    <form action="{{ route('reading-corrections.approve', $correction) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button class="btn btn-xs btn-success">Setujui</button>
                                                    </form>
                                                    <form action="{{ route('reading-corrections.reject', $correction) }}" method="POST" class="d-inline"
                                                          onsubmit="const n = prompt('Alasan menolak koreksi:'); if (!n) return false; this.notes.value = n;">
                                                        @csrf
                                                        <input type="hidden" name="notes">
                                                        <button class="btn btn-xs btn-outline-danger">Tolak</button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title">Riwayat pemakaian {{ $house->house_code }}</h3>
                    <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light"><tr><th>Bulan</th><th class="text-right">Awal</th><th class="text-right">Akhir</th><th class="text-right">m³</th><th class="text-right">Tagihan</th></tr></thead>
                        <tbody>
                            @foreach ($history as $usage)
                                <tr>
                                    <td>{{ $usage->usage_period->translatedFormat('M Y') }}</td>
                                    <td class="text-right">{{ Money::formatQuantity($usage->previous_value) }}</td>
                                    <td class="text-right">{{ Money::formatQuantity($usage->current_value) }}</td>
                                    <td class="text-right">{{ Money::formatQuantity($usage->usage_volume) }}</td>
                                    <td class="text-right">{{ Money::format($usage->calculated_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@extends('adminlte::page')

@use('App\Support\Money')

@php
    $primary = $house->primaryResident();
    $recorded = $existing && $existing->status->value !== 'rejected';
@endphp

@section('title', 'Meter '.$house->house_code)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">{{ $house->house_code }}</h1>
        <a href="{{ route('meter-recording.index', ['period' => $period->format('Y-m')]) }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Daftar</a>
    </div>
@stop

@section('content')
    @include('partials.flash')

    <div class="card">
        <div class="card-body py-2 small">
            <div><i class="fas fa-user text-muted mr-1"></i> {{ $primary?->full_name ?? 'Belum ada nama penghuni' }}</div>
            <div><i class="fas fa-tachometer-alt text-muted mr-1"></i> Meter {{ $meter->meter_number ?? '(tanpa nomor)' }}</div>
            <div class="mt-1">
                Angka sebelumnya:
                <strong style="font-size:1.1rem">{{ Money::formatQuantity($baseline['value']) }}</strong>
                <span class="text-muted">({{ $baseline['reading'] ? $baseline['reading']->reading_period->translatedFormat('F Y') : 'angka awal meter' }})</span>
            </div>
        </div>
    </div>

    @if ($existing && $existing->status->value === 'rejected')
        <div class="alert alert-danger small">
            Pembacaan sebelumnya ({{ Money::formatQuantity($existing->reading_value) }}) <strong>ditolak</strong>: {{ $existing->rejection_reason }}. Silakan catat ulang.
        </div>
    @endif

    @if ($recorded)
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title">Sudah dicatat — {{ $period->translatedFormat('F Y') }}</h3></div>
            <div class="card-body">
                <p class="mb-1">Angka: <strong style="font-size:1.3rem">{{ Money::formatQuantity($existing->reading_value) }}</strong>
                    <span class="badge badge-{{ $existing->status->badge() }}">{{ $existing->status->label() }}</span></p>
                @if ($existing->photo_file_id)
                    <a href="{{ route('files.show', $existing->photo_file_id) }}" target="_blank" rel="noopener" class="small"><i class="fas fa-image mr-1"></i>Lihat foto</a>
                @endif
            </div>
        </div>

        @if ($existing->status->value === 'validated')
            @can('meter-readings.correct')
                <div class="card card-outline card-warning">
                    <div class="card-header"><h3 class="card-title">Angka salah? Ajukan koreksi</h3></div>
                    <form action="{{ route('meter-readings.corrections.store', $existing) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            <div class="form-group">
                                <label for="new_value">Angka yang benar</label>
                                <input id="new_value" name="new_value" type="number" inputmode="decimal" step="0.001" min="0" required
                                       value="{{ old('new_value') }}" class="form-control form-control-lg @error('new_value') is-invalid @enderror">
                                @error('new_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="reason">Alasan</label>
                                <input id="reason" name="reason" type="text" maxlength="500" required value="{{ old('reason') }}"
                                       class="form-control @error('reason') is-invalid @enderror" placeholder="mis. salah ketik angka">
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group mb-0">
                                <label for="correction-photo">Foto baru (opsional)</label>
                                <input id="correction-photo" name="photo" type="file" accept="image/*" capture="environment" class="form-control-file">
                            </div>
                        </div>
                        <div class="card-footer"><button class="btn btn-warning btn-block">Ajukan koreksi</button></div>
                    </form>
                </div>
            @endcan
        @endif
    @else
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title">Catat meter — {{ $period->translatedFormat('F Y') }}</h3></div>
            <form action="{{ route('meter-recording.store', $house) }}" method="POST" enctype="multipart/form-data" id="record-form">
                @csrf
                <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
                <div class="card-body">
                    <div class="form-group">
                        <label for="reading_value">Angka meter sekarang</label>
                        <input id="reading_value" name="reading_value" type="number" inputmode="decimal" step="0.001" min="0" required autofocus
                               value="{{ old('reading_value') }}"
                               class="form-control form-control-lg text-center font-weight-bold @error('reading_value') is-invalid @enderror"
                               style="font-size:1.8rem" data-previous="{{ (string) $baseline['value'] }}" data-carried="{{ (string) $baseline['carried'] }}">
                        @error('reading_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small id="usage-preview" class="form-text text-muted"></small>
                    </div>

                    <div class="form-group">
                        <label for="photo">Foto meteran <span class="text-danger">*</span></label>
                        <input id="photo" name="photo" type="file" accept="image/*" capture="environment" required
                               class="form-control-file @error('photo') is-invalid @enderror">
                        @error('photo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <img id="photo-preview" alt="" class="img-fluid rounded mt-2 d-none" style="max-height:240px">
                    </div>

                    <div class="form-group mb-0">
                        <label for="notes">Catatan</label>
                        <input id="notes" name="notes" type="text" maxlength="500" value="{{ old('notes') }}" class="form-control" placeholder="opsional, mis. meter berembun">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-lg btn-block"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    @endif
@stop

@push('js')
<script>
    (function () {
        const input = document.getElementById('reading_value');
        const preview = document.getElementById('usage-preview');
        if (input && preview) {
            const previous = parseFloat(input.dataset.previous || '0');
            const carried = parseFloat(input.dataset.carried || '0');
            const update = function () {
                const value = parseFloat(input.value);
                if (isNaN(value)) { preview.textContent = ''; preview.className = 'form-text text-muted'; return; }
                const usage = Math.round((value - previous + carried) * 1000) / 1000;
                if (value < previous) {
                    preview.textContent = 'Angka lebih kecil dari sebelumnya — periksa lagi. Bila benar, pembacaan akan diperiksa pengurus.';
                    preview.className = 'form-text text-danger';
                } else {
                    preview.textContent = 'Pemakaian: ' + usage.toLocaleString('id-ID') + ' m³';
                    preview.className = 'form-text text-success';
                }
            };
            input.addEventListener('input', update);
            update();
        }

        const photo = document.getElementById('photo');
        const img = document.getElementById('photo-preview');
        if (photo && img) {
            photo.addEventListener('change', function () {
                const file = photo.files && photo.files[0];
                if (!file) { img.classList.add('d-none'); return; }
                img.src = URL.createObjectURL(file);
                img.classList.remove('d-none');
            });
        }
    })();
</script>
@endpush

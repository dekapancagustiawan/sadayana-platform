@extends('adminlte::page')

@php $editing = $house->exists; @endphp
@section('title', $editing ? 'Ubah Rumah' : 'Tambah Rumah')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-house-user mr-2 text-primary"></i>{{ $editing ? 'Ubah Rumah '.$house->house_code : 'Tambah Rumah' }}</h1>
        <a href="{{ $editing ? route('houses.show', $house) : route('houses.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>
@stop

@section('content')
    @include('partials.flash')

    @if ($blocks->isEmpty())
        <div class="alert alert-warning">Belum ada blok. <a href="{{ route('blocks.create') }}">Tambahkan blok</a> terlebih dahulu.</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card card-outline card-primary">
                <form action="{{ $editing ? route('houses.update', $house) : route('houses.store') }}" method="POST" novalidate>
                    @csrf
                    @if ($editing) @method('PUT') @endif

                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-sm-6">
                                <label for="block_id">Blok <span class="text-danger">*</span></label>
                                <select id="block_id" name="block_id" required class="form-control @error('block_id') is-invalid @enderror">
                                    <option value="">— pilih blok —</option>
                                    @foreach ($blocks as $block)
                                        <option value="{{ $block->id }}" @selected(old('block_id', $house->block_id) === $block->id)>{{ $block->label() }}</option>
                                    @endforeach
                                </select>
                                @error('block_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="house_number">Nomor rumah <span class="text-danger">*</span></label>
                                <input id="house_number" name="house_number" type="text" maxlength="30" required
                                       value="{{ old('house_number', $house->house_number) }}"
                                       class="form-control text-uppercase @error('house_number') is-invalid @enderror" placeholder="mis. 12">
                                @error('house_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Alamat</label>
                            <textarea id="address" name="address" rows="2"
                                      class="form-control @error('address') is-invalid @enderror"
                                      placeholder="opsional — kosongkan bila cukup blok & nomor">{{ old('address', $house->address) }}</textarea>
                            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-row">
                            <div class="form-group col-sm-4">
                                <label for="house_type">Tipe</label>
                                <input id="house_type" name="house_type" type="text" maxlength="30"
                                       value="{{ old('house_type', $house->house_type) }}"
                                       class="form-control @error('house_type') is-invalid @enderror" placeholder="mis. 36/72">
                                @error('house_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-sm-4">
                                <label for="area_size">Luas tanah (m²)</label>
                                <input id="area_size" name="area_size" type="number" step="0.01" min="0"
                                       value="{{ old('area_size', $house->area_size) }}"
                                       class="form-control @error('area_size') is-invalid @enderror">
                                @error('area_size') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-sm-4">
                                <label for="occupancy_status">Status hunian</label>
                                <select id="occupancy_status" name="occupancy_status" class="form-control @error('occupancy_status') is-invalid @enderror">
                                    @foreach (\App\Enums\Resident\OccupancyStatus::cases() as $status)
                                        <option value="{{ $status->value }}" @selected(old('occupancy_status', $house->occupancy_status?->value ?? 'vacant') === $status->value)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Dihuni/kosong diperbarui otomatis saat penghuni masuk/keluar.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Catatan</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $house->notes) }}</textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $house->is_active))>
                            <label class="custom-control-label" for="is_active">Rumah aktif (ikut ditagih & muncul di daftar petugas)</label>
                        </div>
                    </div>

                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

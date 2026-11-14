@extends('adminlte::page')

@php($editing = $resident->exists)

@section('title', $editing ? 'Ubah Penghuni' : 'Tambah Penghuni')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-user mr-2 text-primary"></i>{{ $editing ? 'Ubah Penghuni' : 'Tambah Penghuni' }}</h1>
        <a href="{{ $editing ? route('residents.show', $resident) : route('residents.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        <div class="col-lg-8">
            <div class="card card-outline card-primary">
                <form action="{{ $editing ? route('residents.update', $resident) : route('residents.store') }}" method="POST" novalidate>
                    @csrf
                    @if ($editing) @method('PUT') @endif

                    <div class="card-body">
                        <div class="form-group">
                            <label for="full_name">Nama lengkap <span class="text-danger">*</span></label>
                            <input id="full_name" name="full_name" type="text" maxlength="150" required
                                   value="{{ old('full_name', $resident->full_name) }}"
                                   class="form-control @error('full_name') is-invalid @enderror">
                            @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-row">
                            <div class="form-group col-sm-6">
                                <label for="phone">Nomor HP</label>
                                <input id="phone" name="phone" type="tel" value="{{ old('phone', $resident->displayPhone()) }}" placeholder="08xxxxxxxxxx"
                                       class="form-control @error('phone') is-invalid @enderror">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email', $resident->email) }}"
                                       class="form-control @error('email') is-invalid @enderror">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-sm-6">
                                <label for="resident_number">No. warga</label>
                                <input id="resident_number" name="resident_number" type="text" maxlength="50"
                                       value="{{ old('resident_number', $resident->resident_number) }}"
                                       class="form-control @error('resident_number') is-invalid @enderror" placeholder="opsional">
                                @error('resident_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="birth_date">Tanggal lahir</label>
                                <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $resident->birth_date?->toDateString()) }}"
                                       class="form-control @error('birth_date') is-invalid @enderror">
                                @error('birth_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        @can('residents.view-identity')
                            <div class="form-group">
                                <label for="identity_number">Nomor identitas (NIK) <i class="fas fa-lock text-muted" title="Disimpan terenkripsi"></i></label>
                                <input id="identity_number" name="identity_number" type="text" maxlength="50" autocomplete="off"
                                       class="form-control @error('identity_number') is-invalid @enderror"
                                       placeholder="{{ $editing && $resident->getRawOriginal('identity_number') ? 'Tersimpan — kosongkan bila tidak diubah' : 'opsional' }}">
                                @error('identity_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endcan

                        @if ($editing)
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    @foreach (\App\Enums\Resident\ResidentStatus::cases() as $status)
                                        <option value="{{ $status->value }}" @selected(old('status', $resident->status->value) === $status->value)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Status "Pindah" diatur otomatis saat semua periode huniannya ditutup.</small>
                            </div>
                        @endif

                        <div class="form-group mb-0">
                            <label for="notes">Catatan</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $resident->notes) }}</textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
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

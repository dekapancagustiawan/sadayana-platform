@extends('adminlte::page')

@php($editing = $block->exists)

@section('title', $editing ? 'Ubah Blok' : 'Tambah Blok')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-th-large mr-2 text-primary"></i>{{ $editing ? 'Ubah Blok '.$block->code : 'Tambah Blok' }}</h1>
        <a href="{{ route('blocks.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        <div class="col-md-8 col-lg-6">
            <div class="card card-outline card-primary">
                <form action="{{ $editing ? route('blocks.update', $block) : route('blocks.store') }}" method="POST" novalidate>
                    @csrf
                    @if ($editing) @method('PUT') @endif

                    <div class="card-body">
                        <div class="form-group">
                            <label for="code">Kode blok <span class="text-danger">*</span></label>
                            <input id="code" name="code" type="text" maxlength="30" required
                                   value="{{ old('code', $block->code) }}"
                                   class="form-control text-uppercase @error('code') is-invalid @enderror" placeholder="mis. J">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if ($editing)
                                <small class="form-text text-muted">Mengganti kode ikut mengubah kode semua rumah di blok ini (mis. J-12).</small>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="name">Nama blok</label>
                            <input id="name" name="name" type="text" maxlength="100"
                                   value="{{ old('name', $block->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" placeholder="opsional, mis. Cluster Melati">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label for="description">Keterangan</label>
                            <textarea id="description" name="description" rows="2"
                                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $block->description) }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group mb-0">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control @error('status') is-invalid @enderror">
                                @foreach (\App\Enums\Resident\BlockStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', $block->status?->value) === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
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

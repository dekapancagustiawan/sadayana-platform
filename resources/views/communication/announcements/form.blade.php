@extends('adminlte::page')

@php $editing = $announcement->exists; @endphp
@section('title', $editing ? 'Ubah Pengumuman' : 'Buat Pengumuman')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-bullhorn mr-2 text-primary"></i>{{ $editing ? 'Ubah Pengumuman' : 'Buat Pengumuman' }}</h1>
@stop

@section('content')
    @include('partials.flash')
    <div class="row"><div class="col-lg-8">
        <form action="{{ $editing ? route('announcements.update', $announcement) : route('announcements.store') }}" method="POST" class="card card-outline card-primary">@csrf
            @if ($editing) @method('PUT') @endif
            <div class="card-body">
                <div class="form-group"><label>Judul</label><input name="title" class="form-control @error('title') is-invalid @enderror" required maxlength="150" value="{{ old('title', $announcement->title) }}">@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="form-group"><label>Isi</label><textarea name="content" rows="8" class="form-control @error('content') is-invalid @enderror" required>{{ old('content', $announcement->content) }}</textarea>@error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="form-row">
                    <div class="form-group col-sm-4"><label>Tayang</label><input type="datetime-local" name="publish_at" class="form-control" required value="{{ old('publish_at', $announcement->publish_at?->format('Y-m-d\TH:i')) }}"></div>
                    <div class="form-group col-sm-4"><label>Berakhir (opsional)</label><input type="datetime-local" name="expires_at" class="form-control @error('expires_at') is-invalid @enderror" value="{{ old('expires_at', $announcement->expires_at?->format('Y-m-d\TH:i')) }}">@error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="form-group col-sm-4"><label>Status</label>
                        <select name="status" class="form-control">@foreach (['published' => 'Tayang', 'draft' => 'Draft', 'archived' => 'Arsip'] as $v => $l)<option value="{{ $v }}" @selected(old('status', $announcement->status) === $v)>{{ $l }}</option>@endforeach</select></div>
                </div>
                <small class="text-muted">Saat pertama ditayangkan, semua warga mendapat notifikasi.</small>
            </div>
            <div class="card-footer text-right"><button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button></div>
        </form>
    </div></div>
@stop

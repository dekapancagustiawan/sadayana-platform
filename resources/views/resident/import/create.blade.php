@extends('adminlte::page')

@section('title', 'Import Rumah & Penghuni')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-file-import mr-2 text-primary"></i>Import Rumah & Penghuni</h1>
        <a href="{{ route('houses.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Daftar rumah</a>
    </div>
@stop

@section('content')
    @include('partials.flash')

    @if ($importErrors = session('import_errors'))
        <div class="card card-outline card-danger">
            <div class="card-header"><h3 class="card-title text-danger">Kesalahan di file</h3></div>
            <div class="card-body" style="max-height:320px;overflow:auto">
                <ul class="mb-0 small">
                    @foreach ($importErrors as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-6">
            <div class="card card-outline card-primary">
                <form action="{{ route('houses.import.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="file">File CSV</label>
                            <input id="file" name="file" type="file" accept=".csv,text/csv" required
                                   class="form-control-file @error('file') is-invalid @enderror">
                            @error('file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <small class="form-text text-muted">Dari Excel: <em>File → Save As → CSV (comma delimited)</em>. Maks. 2 MB.</small>
                        </div>
                        <p class="small text-muted mb-0">
                            Seluruh baris diperiksa dulu. Bila ada satu saja kesalahan, <strong>tidak ada data yang disimpan</strong>
                            dan daftar kesalahannya ditampilkan. File yang sama aman diimport ulang — rumah dan penghuni yang
                            sudah tercatat tidak digandakan.
                        </p>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('houses.import.template') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-download mr-1"></i> Unduh contoh CSV</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i> Import</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Format kolom</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0 small">
                        <thead class="thead-light"><tr><th>Kolom</th><th>Isi</th></tr></thead>
                        <tbody>
                            <tr><td><code>blok</code> <span class="text-danger">*</span></td><td>Kode blok, mis. <code>J</code>. Blok baru dibuat otomatis.</td></tr>
                            <tr><td><code>nomor</code> <span class="text-danger">*</span></td><td>Nomor rumah, mis. <code>12</code>.</td></tr>
                            <tr><td><code>nama</code></td><td>Nama pemilik/penghuni. Kosong = rumah saja.</td></tr>
                            <tr><td><code>no_hp</code></td><td>Format bebas: <code>0812…</code>, <code>+62 812…</code>.</td></tr>
                            <tr><td><code>email</code></td><td>Opsional.</td></tr>
                            <tr><td><code>hubungan</code></td><td><code>pemilik</code> (bawaan), <code>penyewa</code>, <code>keluarga</code>.</td></tr>
                            <tr><td><code>tanggal_mulai</code></td><td><code>DD/MM/YYYY</code>; kosong = hari ini.</td></tr>
                            <tr><td><code>status_hunian</code></td><td><code>renovasi</code> bila sedang direnovasi; dihuni/kosong diisi otomatis.</td></tr>
                            <tr><td><code>catatan</code></td><td>Catatan rumah.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Judul kolom boleh huruf besar/kecil dan urutan bebas. Pemisah koma atau titik koma sama-sama diterima.
                    Satu rumah dengan beberapa penghuni = beberapa baris dengan blok & nomor yang sama.
                </div>
            </div>
        </div>
    </div>
@stop

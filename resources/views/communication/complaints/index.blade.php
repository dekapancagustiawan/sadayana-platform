@extends('adminlte::page')

@section('title', 'Pengaduan')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-comment-dots mr-2 text-primary"></i>Pengaduan</h1>
@stop

@section('content')
    @include('partials.flash')
    <div class="row">
        @can('complaints.submit')
            <div class="col-lg-4">
                <form action="{{ route('complaints.store') }}" method="POST" enctype="multipart/form-data" class="card card-outline card-warning">@csrf
                    <div class="card-header"><h3 class="card-title">Kirim pengaduan</h3></div>
                    <div class="card-body">
                        <a href="#" class="btn btn-outline-success btn-sm btn-block mb-3" onclick="document.getElementById('category').value='waste_missed'; document.getElementById('description').value='Sampah di rumah saya belum diambil sejak '; document.getElementById('description').focus(); return false;">
                            <i class="fas fa-recycle mr-1"></i> Lapor: sampah belum diambil
                        </a>
                        <div class="form-group"><label class="small">Kategori</label>
                            <select id="category" name="category" class="form-control" required>@foreach ($categories as $c)<option value="{{ $c->value }}" @selected(old('category') === $c->value)>{{ $c->label() }}</option>@endforeach</select></div>
                        @if ($houses->count() > 1)
                            <div class="form-group"><label class="small">Rumah</label>
                                <select name="house_id" class="form-control">@foreach ($houses as $h)<option value="{{ $h->id }}">{{ $h->house_code }}</option>@endforeach</select></div>
                        @endif
                        <div class="form-group"><label class="small">Isi pengaduan</label><textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror" required maxlength="2000">{{ old('description') }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="form-group mb-0"><label class="small">Foto (opsional)</label><input type="file" name="attachment" accept="image/*" capture="environment" class="form-control-file"></div>
                    </div>
                    <div class="card-footer"><button class="btn btn-warning btn-block">Kirim</button></div>
                </form>
            </div>
        @endcan
        <div class="col">
            @if ($manage)
                <form method="GET" class="form-inline mb-2">
                    <select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()"><option value="">Semua status</option>@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach</select>
                    <select name="category" class="form-control form-control-sm" onchange="this.form.submit()"><option value="">Semua kategori</option>@foreach ($categories as $c)<option value="{{ $c->value }}" @selected(request('category') === $c->value)>{{ $c->label() }}</option>@endforeach</select>
                </form>
            @endif
            <div class="card"><div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>No.</th><th>Tanggal</th><th>Rumah</th><th>Kategori</th><th>Isi</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($complaints as $complaint)
                            <tr>
                                <td><a href="{{ route('complaints.show', $complaint) }}">{{ $complaint->complaint_number }}</a></td>
                                <td class="text-nowrap">{{ $complaint->created_at->format('d/m/Y') }}</td>
                                <td>{{ $complaint->house?->house_code ?? '—' }}</td>
                                <td>{{ $complaint->category->label() }}</td>
                                <td>{{ str($complaint->description)->limit(60) }}</td>
                                <td><span class="badge badge-{{ $complaint->status->badge() }}">{{ $complaint->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pengaduan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($complaints->hasPages())<div class="card-footer">{{ $complaints->links('partials.pagination') }}</div>@endif
            </div>
        </div>
    </div>
@stop

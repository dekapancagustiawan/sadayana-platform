@extends('adminlte::page')

@section('title', 'Pengaduan '.$complaint->complaint_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">{{ $complaint->complaint_number }} <span class="badge badge-{{ $complaint->status->badge() }} align-middle" style="font-size:.9rem">{{ $complaint->status->label() }}</span></h1>
        <a href="{{ route('complaints.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Daftar</a>
    </div>
@stop

@section('content')
    @include('partials.flash')
    <div class="row">
        <div class="col-lg-7">
            <div class="card"><div class="card-body">
                <p class="text-muted small mb-2">{{ $complaint->category->label() }} · {{ $complaint->house?->house_code ?? 'tanpa rumah' }} · {{ $complaint->submitter?->name }} · {{ $complaint->created_at->format('d/m/Y H:i') }}</p>
                <div style="white-space:pre-line">{{ $complaint->description }}</div>
                @if ($complaint->attachment_file_id)
                    <a href="{{ route('files.show', $complaint->attachment_file_id) }}" target="_blank"><img src="{{ route('files.show', $complaint->attachment_file_id) }}" alt="Lampiran" class="img-fluid rounded mt-3" style="max-height:320px"></a>
                @endif
            </div></div>
            @if ($complaint->resolution_notes)
                <div class="callout callout-success"><h5>Tanggapan pengurus</h5><p class="mb-0" style="white-space:pre-line">{{ $complaint->resolution_notes }}</p></div>
            @endif
        </div>
        @if ($manage)
            <div class="col-lg-5">
                <form action="{{ route('complaints.update', $complaint) }}" method="POST" class="card card-outline card-primary">@csrf @method('PUT')
                    <div class="card-header"><h3 class="card-title">Tindak lanjut</h3></div>
                    <div class="card-body">
                        <div class="form-group"><label class="small">Status</label>
                            <select name="status" class="form-control">@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected($complaint->status === $s)>{{ $s->label() }}</option>@endforeach</select></div>
                        <div class="form-group"><label class="small">Ditangani oleh</label>
                            <select name="assigned_to" class="form-control"><option value="">—</option>@foreach ($staff as $u)<option value="{{ $u->id }}" @selected($complaint->assigned_to === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                        <div class="form-group mb-0"><label class="small">Tanggapan (terlihat oleh pelapor)</label><textarea name="resolution_notes" rows="4" class="form-control">{{ $complaint->resolution_notes }}</textarea></div>
                    </div>
                    <div class="card-footer"><button class="btn btn-primary btn-block">Simpan & beri tahu pelapor</button></div>
                </form>
            </div>
        @endif
    </div>
@stop

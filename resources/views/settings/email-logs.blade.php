@extends('adminlte::page')

@section('title', 'Email Log')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-envelope-open-text mr-2 text-primary"></i>
            Email Log
        </h1>
        <a href="{{ route('settings.index', ['tab' => 'mail']) }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-cog mr-1"></i> Pengaturan Email
        </a>
    </div>
@stop

@section('content')
    <div class="card card-outline card-primary">
        <div class="card-body pb-0">
            <form action="{{ route('email-logs.index') }}" method="GET" class="form-row">
                <div class="form-group col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Cari penerima atau subjek"
                           value="{{ request('search') }}">
                </div>
                <div class="form-group col-md-2">
                    <select name="status" class="form-control">
                        <option value="">Semua status</option>
                        <option value="sent" @selected(request('status') === 'sent')>Terkirim</option>
                        <option value="failed" @selected(request('status') === 'failed')>Gagal</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <select name="type" class="form-control">
                        <option value="">Semua tipe</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i> Filter</button>
                </div>
            </form>
        </div>

        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Waktu</th>
                        <th>Penerima</th>
                        <th>Subjek</th>
                        <th>Tipe</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap text-muted">{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td>{{ $log->recipient_email }}</td>
                            <td>{{ $log->subject }}</td>
                            <td><code>{{ $log->email_type ?? '—' }}</code></td>
                            <td>
                                @if ($log->status === 'sent')
                                    <span class="badge badge-success">Terkirim</span>
                                @else
                                    <span class="badge badge-danger" title="{{ $log->error_message }}">Gagal</span>
                                    @if ($log->error_message)
                                        <div class="small text-muted text-break" style="max-width:420px;">{{ \Illuminate\Support\Str::limit($log->error_message, 160) }}</div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada email tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="card-footer d-flex justify-content-end">
                {{ $logs->links('partials.pagination') }}
            </div>
        @endif
    </div>
@stop

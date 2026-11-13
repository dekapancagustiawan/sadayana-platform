@extends('adminlte::page')

@section('title', __('Audit Logs'))

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-clipboard-list mr-2 text-primary"></i>
            {{ __('Audit Logs') }}
        </h1>
    </div>
@stop

@section('content')

    {{-- Filters --}}
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filters</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('audit-logs.index') }}" method="GET" class="row">
                <div class="col-md-2 mb-3">
                    <label for="user_id">User</label>
                    <select name="user_id" id="user_id" class="form-control">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="action">Action</label>
                    <input type="text" name="action" id="action" class="form-control" value="{{ request('action') }}" placeholder="e.g. login, user.created">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="model_type">Model</label>
                    <select name="model_type" id="model_type" class="form-control">
                        <option value="">All Models</option>
                        @foreach($modelTypes as $type)
                            <option value="{{ $type }}" {{ request('model_type') == $type ? 'selected' : '' }}>
                                {{ class_basename($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Audit Log Table --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list mr-1"></i>
                Log Entries
                <span class="badge badge-secondary ml-2">{{ $logs->total() }}</span>
            </h3>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:160px">Date &amp; Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Model</th>
                        <th style="width:120px">IP Address</th>
                        <th style="width:80px">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            <td>{{ $log->user->name ?? 'System' }}</td>
                            <td><code class="text-primary">{{ $log->action }}</code></td>
                            <td>
                                @if($log->model_type)
                                    <span class="badge badge-secondary">{{ class_basename($log->model_type) }}</span>
                                    @if($log->model_id)
                                        <span class="text-muted">#{{ $log->model_id }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $log->ip_address ?? '—' }}</td>
                            <td>
                                @if($log->payload)
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-outline-secondary btn-view-payload"
                                        data-payload="{{ json_encode($log->payload, JSON_PRETTY_PRINT) }}"
                                        data-toggle="modal"
                                        data-target="#payloadModal"
                                        title="View payload"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-clipboard-list fa-2x mb-2 d-block"></i>
                                No audit log entries found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- Payload Modal --}}
    <div class="modal fade" id="payloadModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-code mr-1"></i> Payload Detail</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <pre id="payloadContent" style="white-space: pre-wrap; word-break: break-word;"></pre>
                </div>
            </div>
        </div>
    </div>

@stop

@section('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.btn-view-payload').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('payloadContent').textContent = this.dataset.payload;
            });
        });
    });
</script>
@stop

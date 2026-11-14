@extends('adminlte::page')

@section('title', 'Notifikasi Saya')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-bell mr-2 text-primary"></i>Notifikasi Saya</h1>
        <form action="{{ route('notifications.read-all') }}" method="POST">@csrf<button class="btn btn-outline-secondary btn-sm">Tandai semua sudah dibaca</button></form>
    </div>
@stop

@section('content')
    @include('partials.flash')
    <div class="list-group mb-3">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}" class="list-group-item list-group-item-action {{ $notification->read_at ? '' : 'list-group-item-info' }}">
                <div class="d-flex justify-content-between">
                    <strong><i class="{{ $notification->data['icon'] ?? 'fas fa-bell' }} mr-1"></i> {{ $notification->data['title'] ?? 'Notifikasi' }}</strong>
                    <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                </div>
                <div class="small">{{ $notification->data['message'] ?? '' }}</div>
            </a>
        @empty
            <div class="list-group-item text-muted text-center py-4">Belum ada notifikasi.</div>
        @endforelse
    </div>
    {{ $notifications->links('partials.pagination') }}
@stop

@extends('adminlte::page')

@section('title', 'Pengumuman')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-bullhorn mr-2 text-primary"></i>Pengumuman</h1>
        @if ($manage)<a href="{{ route('announcements.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i> Buat pengumuman</a>@endif
    </div>
@stop

@section('content')
    @include('partials.flash')
    @forelse ($announcements as $announcement)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><a href="{{ route('announcements.show', $announcement) }}">{{ $announcement->title }}</a></h3>
                <div class="card-tools small text-muted">
                    {{ $announcement->publish_at->translatedFormat('d F Y') }}
                    @if ($manage && $announcement->status !== 'published') <span class="badge badge-secondary">{{ $announcement->status }}</span> @endif
                </div>
            </div>
            <div class="card-body" style="white-space:pre-line">{{ str($announcement->content)->limit(300) }}</div>
        </div>
    @empty
        <div class="callout callout-info">Belum ada pengumuman.</div>
    @endforelse
    {{ $announcements->links('partials.pagination') }}
@stop

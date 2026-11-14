@extends('adminlte::page')

@section('title', $announcement->title)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">{{ $announcement->title }}</h1>
        <div>
            <a href="{{ route('announcements.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Semua</a>
            @can('announcements.manage')<a href="{{ route('announcements.edit', $announcement) }}" class="btn btn-warning btn-sm"><i class="fas fa-edit mr-1"></i> Ubah</a>@endcan
        </div>
    </div>
@stop

@section('content')
    @include('partials.flash')
    <div class="card"><div class="card-body">
        <p class="text-muted small">{{ $announcement->publish_at->translatedFormat('l, d F Y H:i') }}{{ $announcement->expires_at ? ' · berlaku s.d. '.$announcement->expires_at->translatedFormat('d F Y') : '' }}</p>
        <div style="white-space:pre-line; font-size:1.05rem">{{ $announcement->content }}</div>
    </div></div>
@stop

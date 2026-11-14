@extends('adminlte::page')

@section('title', 'Blok')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-th-large mr-2 text-primary"></i>Blok</h1>
        @can('houses.manage')
            <a href="{{ route('blocks.create') }}" class="btn btn-primary btn-sm" id="btn-create-block">
                <i class="fas fa-plus mr-1"></i> Tambah Blok
            </a>
        @endcan
    </div>
@stop

@section('content')
    @include('partials.flash')

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th class="text-right">Jumlah rumah</th>
                        <th class="text-right">Dihuni</th>
                        <th>Status</th>
                        <th style="width:150px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($blocks as $block)
                        <tr>
                            <td class="font-weight-bold">{{ $block->code }}</td>
                            <td>{{ $block->name ?? '—' }}</td>
                            <td class="text-right">{{ $block->houses_count }}</td>
                            <td class="text-right">{{ $block->occupied_count }}</td>
                            <td><span class="badge badge-{{ $block->status->value === 'active' ? 'success' : 'secondary' }}">{{ $block->status->label() }}</span></td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('houses.index', ['block' => $block->id]) }}" class="btn btn-xs btn-outline-primary" title="Lihat rumah">
                                    <i class="fas fa-house-user"></i>
                                </a>
                                @can('houses.manage')
                                    <a href="{{ route('blocks.edit', $block) }}" class="btn btn-xs btn-warning" title="Ubah"><i class="fas fa-edit"></i></a>
                                    @if ($block->houses_count === 0)
                                        <form action="{{ route('blocks.destroy', $block) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Hapus blok {{ $block->code }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Belum ada blok.
                                @can('residents.import')
                                    Tambahkan manual, atau <a href="{{ route('houses.import') }}">import dari CSV</a>.
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

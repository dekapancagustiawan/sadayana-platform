@extends('adminlte::page')

@section('title', 'Penugasan Petugas')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-user-tag mr-2 text-primary"></i>Penugasan Petugas</h1>
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        @can('officer-assignments.manage')
            <div class="col-lg-4">
                <div class="card card-outline card-primary">
                    <div class="card-header"><h3 class="card-title">Tambah penugasan</h3></div>
                    <form action="{{ route('officer-assignments.store') }}" method="POST" novalidate>
                        @csrf
                        <div class="card-body">
                            <div class="form-group">
                                <label for="user_id">Petugas</label>
                                <select id="user_id" name="user_id" class="form-control @error('user_id') is-invalid @enderror">
                                    <option value="">— pilih —</option>
                                    @foreach ($officers as $officer)
                                        <option value="{{ $officer->id }}" @selected((string) old('user_id') === (string) $officer->id)>{{ $officer->name }}</option>
                                    @endforeach
                                </select>
                                @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="assignment_type">Tugas</label>
                                <select id="assignment_type" name="assignment_type" class="form-control @error('assignment_type') is-invalid @enderror">
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected(old('assignment_type') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="block_id">Blok</label>
                                <select id="block_id" name="block_id" class="form-control @error('block_id') is-invalid @enderror">
                                    <option value="">—</option>
                                    @foreach ($blocks as $block)
                                        <option value="{{ $block->id }}" @selected(old('block_id') === $block->id)>{{ $block->label() }}</option>
                                    @endforeach
                                </select>
                                @error('block_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group">
                                <label for="house_id">atau satu rumah</label>
                                <select id="house_id" name="house_id" class="form-control @error('house_id') is-invalid @enderror">
                                    <option value="">—</option>
                                    @foreach ($houses as $house)
                                        <option value="{{ $house->id }}" @selected(old('house_id') === $house->id)>{{ $house->house_code }}</option>
                                    @endforeach
                                </select>
                                @error('house_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-row">
                                <div class="form-group col-6">
                                    <label for="assigned_from">Mulai</label>
                                    <input id="assigned_from" name="assigned_from" type="date" value="{{ old('assigned_from', today()->toDateString()) }}"
                                           class="form-control @error('assigned_from') is-invalid @enderror">
                                    @error('assigned_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-group col-6">
                                    <label for="assigned_until">Sampai</label>
                                    <input id="assigned_until" name="assigned_until" type="date" value="{{ old('assigned_until') }}"
                                           class="form-control @error('assigned_until') is-invalid @enderror">
                                    @error('assigned_until') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ $showEnded ? 'Semua penugasan' : 'Penugasan aktif' }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('officer-assignments.index', $showEnded ? [] : ['ended' => 1]) }}" class="btn btn-tool">
                            {{ $showEnded ? 'Hanya yang aktif' : 'Tampilkan yang sudah berakhir' }}
                        </a>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light"><tr><th>Petugas</th><th>Tugas</th><th>Wilayah</th><th>Periode</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr @class(['text-muted' => ! $assignment->isCurrent()])>
                                    <td>{{ $assignment->user->name }}</td>
                                    <td>{{ $assignment->assignment_type->label() }}</td>
                                    <td>{{ $assignment->block ? $assignment->block->label() : 'Rumah '.$assignment->house?->house_code }}</td>
                                    <td class="text-nowrap">{{ $assignment->assigned_from->format('d/m/Y') }} – {{ $assignment->assigned_until?->format('d/m/Y') ?? 'sekarang' }}</td>
                                    <td class="text-right">
                                        @can('officer-assignments.manage')
                                            @if ($assignment->isCurrent())
                                                <form action="{{ route('officer-assignments.end', $assignment) }}" method="POST" class="d-inline"
                                                      onsubmit="return confirm('Akhiri penugasan ini per hari ini?');">
                                                    @csrf
                                                    <button class="btn btn-xs btn-outline-danger">Akhiri</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada penugasan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($assignments->hasPages())
                    <div class="card-footer">{{ $assignments->links('partials.pagination') }}</div>
                @endif
            </div>
        </div>
    </div>
@stop

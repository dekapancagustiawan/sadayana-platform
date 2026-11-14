@extends('adminlte::page')

@section('title', 'Rumah '.$house->house_code)

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-house-user mr-2 text-primary"></i>{{ $house->house_code }}
            <span class="badge badge-{{ $house->occupancy_status->badge() }} align-middle" style="font-size:.9rem">{{ $house->occupancy_status->label() }}</span>
            @unless ($house->is_active) <span class="badge badge-light align-middle" style="font-size:.9rem">nonaktif</span> @endunless
        </h1>
        <div class="mt-2 mt-sm-0">
            <a href="{{ route('houses.index', ['block' => $house->block_id]) }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Daftar rumah</a>
            @can('houses.manage')
                <a href="{{ route('houses.edit', $house) }}" class="btn btn-warning btn-sm"><i class="fas fa-edit mr-1"></i> Ubah</a>
                @if ($house->occupancies->isEmpty())
                    <form action="{{ route('houses.destroy', $house) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus rumah {{ $house->house_code }}?');">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm"><i class="fas fa-trash mr-1"></i> Hapus</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@stop

@section('content')
    @include('partials.flash')

    @php
        $active = $house->occupancies->filter->isActive();
        $past = $house->occupancies->reject->isActive();
    @endphp

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Data rumah</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Blok</dt><dd class="col-7">{{ $house->block->label() }}</dd>
                        <dt class="col-5">Nomor</dt><dd class="col-7">{{ $house->house_number }}</dd>
                        <dt class="col-5">Tipe</dt><dd class="col-7">{{ $house->house_type ?? '—' }}</dd>
                        <dt class="col-5">Luas</dt><dd class="col-7">{{ $house->area_size ? rtrim(rtrim($house->area_size, '0'), '.').' m²' : '—' }}</dd>
                        <dt class="col-5">Alamat</dt><dd class="col-7">{{ $house->address ?? '—' }}</dd>
                        @if ($house->notes)
                            <dt class="col-5">Catatan</dt><dd class="col-7" style="white-space:pre-line">{{ $house->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @canany(['meter-readings.view', 'water-customers.manage'])
                @include('water.partials.house-card')
            @endcanany

            @can('residents.manage')
                <div class="card card-outline card-success">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-sign-in-alt mr-1"></i> Penghuni masuk</h3></div>
                    <form id="move-in-form" action="{{ route('houses.occupancies.store', $house) }}" method="POST" data-mode="{{ old('full_name') ? 'new' : 'existing' }}">
                        @csrf
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="mode-existing" name="_mode" value="existing" class="custom-control-input">
                                    <label class="custom-control-label" for="mode-existing">Sudah terdaftar</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="mode-new" name="_mode" value="new" class="custom-control-input">
                                    <label class="custom-control-label" for="mode-new">Penghuni baru</label>
                                </div>
                            </div>

                            <div class="form-group" data-mode-panel="existing">
                                <label for="resident_id">Penghuni</label>
                                <select id="resident_id" name="resident_id" class="form-control @error('resident_id') is-invalid @enderror">
                                    <option value="">— pilih —</option>
                                    @foreach ($residents as $resident)
                                        <option value="{{ $resident->id }}" @selected(old('resident_id') === $resident->id)>{{ $resident->full_name }}{{ $resident->phone ? ' · '.$resident->displayPhone() : '' }}</option>
                                    @endforeach
                                </select>
                                @error('resident_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div data-mode-panel="new">
                                <div class="form-group">
                                    <label for="full_name">Nama lengkap</label>
                                    <input id="full_name" name="full_name" type="text" maxlength="150" value="{{ old('full_name') }}"
                                           class="form-control @error('full_name') is-invalid @enderror">
                                    @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-group">
                                    <label for="phone">Nomor HP</label>
                                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx"
                                           class="form-control @error('phone') is-invalid @enderror">
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-6">
                                    <label for="relationship_type">Hubungan</label>
                                    <select id="relationship_type" name="relationship_type" class="form-control">
                                        @foreach ($relationshipTypes as $type)
                                            <option value="{{ $type->value }}" @selected(old('relationship_type', 'owner') === $type->value)>{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-6">
                                    <label for="start_date">Mulai menghuni</label>
                                    <input id="start_date" name="start_date" type="date" max="{{ today()->toDateString() }}"
                                           value="{{ old('start_date', today()->toDateString()) }}"
                                           class="form-control @error('start_date') is-invalid @enderror">
                                    @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="is_primary_contact" name="is_primary_contact" value="1" @checked(old('is_primary_contact'))>
                                <label class="custom-control-label" for="is_primary_contact">Jadikan kontak utama rumah</label>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i> Catat penghuni masuk</button>
                        </div>
                    </form>
                </div>
            @endcan
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-users mr-1"></i> Penghuni saat ini</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr><th>Nama</th><th>Hubungan</th><th>No. HP</th><th>Sejak</th><th>Akun</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($active as $occupancy)
                                <tr>
                                    <td>
                                        <a href="{{ route('residents.show', $occupancy->resident) }}">{{ $occupancy->resident->full_name }}</a>
                                        @if ($occupancy->is_primary_contact) <span class="badge badge-info">kontak utama</span> @endif
                                    </td>
                                    <td>{{ $occupancy->relationship_type->label() }}</td>
                                    <td class="text-nowrap">{{ $occupancy->resident->displayPhone() ?? '—' }}</td>
                                    <td class="text-nowrap">{{ $occupancy->start_date->format('d/m/Y') }}</td>
                                    <td>
                                        @if ($occupancy->resident->users->isNotEmpty())
                                            <span class="badge badge-success"><i class="fas fa-user-check"></i> ada</span>
                                        @else
                                            <span class="badge badge-light">belum</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @can('residents.manage')
                                            <button type="button" class="btn btn-xs btn-outline-danger" data-toggle="modal" data-target="#move-out-{{ $occupancy->id }}">
                                                <i class="fas fa-sign-out-alt"></i> Keluar
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada penghuni tercatat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-1"></i> Riwayat penghuni ({{ $past->count() }})</h3>
                    <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light"><tr><th>Nama</th><th>Hubungan</th><th>Periode</th><th>Alasan keluar</th></tr></thead>
                        <tbody>
                            @forelse ($past as $occupancy)
                                <tr>
                                    <td><a href="{{ route('residents.show', $occupancy->resident) }}">{{ $occupancy->resident->full_name }}</a></td>
                                    <td>{{ $occupancy->relationship_type->label() }}</td>
                                    <td class="text-nowrap">{{ $occupancy->start_date->format('d/m/Y') }} – {{ $occupancy->end_date?->format('d/m/Y') }}</td>
                                    <td>{{ $occupancy->end_reason ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada riwayat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @can('residents.manage')
        @foreach ($active as $occupancy)
            <div class="modal fade" id="move-out-{{ $occupancy->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form class="modal-content" action="{{ route('occupancies.end', $occupancy) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Penghuni keluar — {{ $occupancy->resident->full_name }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Riwayat hunian dan tagihan lama tetap tersimpan. Akses akun warga ke rumah ini otomatis berhenti.</p>
                            <div class="form-group">
                                <label>Tanggal keluar</label>
                                <input type="date" name="end_date" class="form-control" required
                                       min="{{ $occupancy->start_date->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}">
                            </div>
                            <div class="form-group">
                                <label>Alasan</label>
                                <input type="text" name="end_reason" class="form-control" maxlength="500" placeholder="mis. pindah rumah, rumah dijual">
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="deactivate_accounts" value="0">
                                <input type="checkbox" class="custom-control-input" id="deactivate-{{ $occupancy->id }}" name="deactivate_accounts" value="1">
                                <label class="custom-control-label" for="deactivate-{{ $occupancy->id }}">Nonaktifkan akun warganya bila tidak lagi menghuni rumah lain</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">Catat keluar</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan
@stop

@push('js')
<script>
    // Form penghuni masuk: tampilkan isian sesuai pilihan, nonaktifkan yang
    // tersembunyi supaya tidak ikut terkirim.
    (function () {
        const form = document.getElementById('move-in-form');
        if (!form) return;

        function apply(mode) {
            form.querySelectorAll('[data-mode-panel]').forEach(function (panel) {
                const on = panel.dataset.modePanel === mode;
                panel.style.display = on ? '' : 'none';
                panel.querySelectorAll('input, select').forEach(function (el) { el.disabled = !on; });
            });
            form.querySelector('#mode-' + mode).checked = true;
        }

        form.querySelectorAll('input[name=_mode]').forEach(function (radio) {
            radio.addEventListener('change', function () { apply(radio.value); });
        });
        apply(form.dataset.mode);
    })();
</script>
@endpush

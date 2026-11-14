@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Iuran Warga')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-hand-holding-usd mr-2 text-warning"></i>Iuran Warga</h1>
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        <div class="col-lg-8">
            @forelse ($types as $type)
                @php $currentPeriod = $current[$type->id] ?? null; @endphp
                <div class="card {{ $type->status === 'active' ? 'card-outline card-warning' : 'collapsed-card' }}">
                    <div class="card-header">
                        <h3 class="card-title">
                            {{ $type->name }}
                            <span class="badge badge-light">{{ $type->contribution_category === 'routine' ? 'bulanan' : 'sekali tagih' }}</span>
                            <span class="badge badge-light">{{ $type->applies_to === 'all_houses' ? 'semua rumah' : $type->assignments->count().' rumah' }}</span>
                            @if ($type->status !== 'active') <span class="badge badge-secondary">nonaktif</span> @endif
                        </h3>
                        <div class="card-tools">
                            <strong>{{ $currentPeriod ? Money::format($currentPeriod->amount) : "—" }}</strong>
                            @can('contributions.manage')
                                <form action="{{ route('contributions.toggle', $type) }}" method="POST" class="d-inline ml-2">@csrf
                                    <button class="btn btn-tool" title="{{ $type->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}"><i class="fas fa-power-off"></i></button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    <div class="card-body small">
                        @if ($type->description) <p>{{ $type->description }}</p> @endif
                        <strong>Riwayat nominal:</strong>
                        @foreach ($type->periods as $period)
                            <span class="badge badge-light">{{ Money::format($period->amount) }} · {{ $period->effective_from->format('m/Y') }}{{ $period->effective_until ? '–'.$period->effective_until->format('m/Y') : '→' }}</span>
                        @endforeach

                        @can('contributions.manage')
                            <form action="{{ route('contributions.periods.store', $type) }}" method="POST" class="form-inline mt-2">@csrf
                                <input type="month" name="effective_from" class="form-control form-control-sm mr-1" required value="{{ now()->addMonthNoOverflow()->format('Y-m') }}">
                                <input type="number" name="amount" class="form-control form-control-sm mr-1" min="0" required placeholder="Nominal">
                                <button class="btn btn-sm btn-outline-warning">{{ $type->contribution_category === 'routine' ? 'Ubah nominal' : 'Tagih lagi' }}</button>
                            </form>
                            @if ($type->applies_to === 'assigned')
                                <form action="{{ route('contributions.assign', $type) }}" method="POST" class="mt-2">@csrf
                                    <div class="form-row">
                                        <div class="col-md-6"><select name="house_ids[]" multiple class="form-control form-control-sm" size="4" required>
                                            @foreach ($houses as $house)<option value="{{ $house->id }}">{{ $house->house_code }}</option>@endforeach
                                        </select></div>
                                        <div class="col-md-6">
                                            <input type="date" name="assigned_from" class="form-control form-control-sm mb-1" value="{{ now()->startOfMonth()->toDateString() }}" required>
                                            <input type="number" name="custom_amount" class="form-control form-control-sm mb-1" min="0" placeholder="Nominal khusus (opsional)">
                                            <button class="btn btn-sm btn-outline-secondary btn-block">Tetapkan rumah</button>
                                        </div>
                                    </div>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <div class="callout callout-info">Belum ada jenis iuran. Contoh: <em>Iuran Perelek Rp5.000/bulan</em>.</div>
            @endforelse
        </div>

        @can('contributions.manage')
            <div class="col-lg-4">
                <div class="card card-outline card-success">
                    <div class="card-header"><h3 class="card-title">Jenis iuran baru</h3></div>
                    <form action="{{ route('contributions.store') }}" method="POST">@csrf
                        <div class="card-body">
                            <div class="form-group"><label class="small">Nama</label><input name="name" class="form-control" required maxlength="150" placeholder="mis. Iuran 17 Agustus" value="{{ old('name') }}"></div>
                            <div class="form-group"><label class="small">Keterangan</label><input name="description" class="form-control" maxlength="1000" value="{{ old('description') }}"></div>
                            <div class="form-row">
                                <div class="form-group col-6"><label class="small">Jenis</label>
                                    <select name="contribution_category" class="form-control"><option value="routine">Bulanan</option><option value="incidental">Sekali tagih</option></select></div>
                                <div class="form-group col-6"><label class="small">Berlaku untuk</label>
                                    <select name="applies_to" class="form-control"><option value="all_houses">Semua rumah</option><option value="assigned">Rumah tertentu</option></select></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-6"><label class="small">Nominal (Rp)</label><input type="number" name="amount" min="0" class="form-control" required value="{{ old('amount') }}"></div>
                                <div class="form-group col-6"><label class="small">Mulai / bulan tagih</label><input type="month" name="effective_from" class="form-control" required value="{{ now()->format('Y-m') }}"></div>
                            </div>
                        </div>
                        <div class="card-footer"><button class="btn btn-success btn-block">Simpan</button></div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@stop

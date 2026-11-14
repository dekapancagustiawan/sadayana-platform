@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Layanan Sampah')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-recycle mr-2 text-success"></i>Layanan Sampah</h1>
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-outline card-success">
                <div class="card-header"><h3 class="card-title">Iuran sampah</h3></div>
                <div class="card-body">
                    @if ($fee)
                        <div class="h3 mb-0">{{ Money::format($fee->monthly_amount) }} <small class="text-muted">/ bulan</small></div>
                        <small class="text-muted">berlaku sejak {{ $fee->effective_from->format('d/m/Y') }} · ditagih akhir bulan</small>
                    @else
                        <p class="text-danger mb-0">Iuran sampah belum diatur.</p>
                    @endif
                    @if ($fees->count() > 1)
                        <ul class="small text-muted mt-2 mb-0 pl-3">
                            @foreach ($fees->skip(1) as $old)
                                <li>{{ Money::format($old->monthly_amount) }} ({{ $old->effective_from->format('m/Y') }} – {{ $old->effective_until?->format('m/Y') }})</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @can('waste.manage')
                    <form action="{{ route('waste.fees.store') }}" method="POST" class="card-footer">
                        @csrf
                        <div class="form-row">
                            <div class="col-6"><input type="month" name="effective_from" class="form-control form-control-sm" required value="{{ now()->addMonthNoOverflow()->format('Y-m') }}" title="Berlaku mulai"></div>
                            <div class="col-6"><input type="number" name="monthly_amount" class="form-control form-control-sm" min="0" required placeholder="Rp / bulan" value="{{ $fee ? (int) $fee->monthly_amount : 30000 }}"></div>
                        </div>
                        <button class="btn btn-success btn-sm btn-block mt-2">{{ $fee ? 'Ubah iuran (periode baru)' : 'Atur iuran' }}</button>
                    </form>
                @endcan
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Pengambilan terlewat terakhir</h3></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($recentMissed as $record)
                        <li class="list-group-item py-2">{{ $record->service_date->format('d/m') }} · <strong>{{ $record->house->house_code }}</strong> — {{ \App\Services\Waste\WasteService::STATUSES[$record->status] ?? $record->status }}</li>
                    @empty
                        <li class="list-group-item text-muted">Tidak ada.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-8">
            <form method="GET" class="mb-2">
                <select name="block" class="form-control form-control-sm d-inline-block w-auto" onchange="this.form.submit()">
                    <option value="">Semua blok</option>
                    @foreach ($blocks as $block)
                        <option value="{{ $block->id }}" @selected(request('block') === $block->id)>{{ $block->label() }}</option>
                    @endforeach
                </select>
                <span class="ml-2 small text-muted">{{ $subscriptions->count() }} rumah berlangganan dari {{ $houses->count() }} rumah</span>
            </form>

            <form action="{{ route('waste.bulk-subscribe') }}" method="POST" id="bulk-form">
                @csrf
                <div class="card">
                    <div class="card-body p-0 table-responsive" style="max-height:600px">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light"><tr>
                                @can('waste.manage')<th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)"></th>@endcan
                                <th>Rumah</th><th>Penghuni</th><th>Langganan sampah</th><th></th>
                            </tr></thead>
                            <tbody>
                                @foreach ($houses as $house)
                                    @php $sub = $subscriptions->get($house->id); @endphp
                                    <tr>
                                        @can('waste.manage')<td>@unless ($sub)<input type="checkbox" class="pick" name="house_ids[]" value="{{ $house->id }}">@endunless</td>@endcan
                                        <td class="font-weight-bold">{{ $house->house_code }}</td>
                                        <td>{{ $house->primaryResident()?->full_name ?? '—' }}</td>
                                        <td>
                                            @if ($sub)
                                                <span class="badge badge-success">aktif sejak {{ $sub->start_date->format('d/m/Y') }}</span>
                                            @else
                                                <span class="badge badge-light">tidak ikut</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($sub)
                                                @can('waste.manage')
                                                    <button form="end-{{ $sub->id }}" class="btn btn-xs btn-outline-danger" onclick="return confirm('Hentikan langganan sampah {{ $house->house_code }} per hari ini?')">Berhenti</button>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @can('waste.manage')
                        <div class="card-footer form-inline">
                            <label class="mr-2 small">Daftarkan yang dicentang mulai</label>
                            <input type="date" name="start_date" class="form-control form-control-sm mr-2" value="{{ now()->startOfMonth()->toDateString() }}" required>
                            <button class="btn btn-success btn-sm">Daftarkan</button>
                        </div>
                    @endcan
                </div>
            </form>
            @can('waste.manage')
                @foreach ($subscriptions as $sub)
                    <form id="end-{{ $sub->id }}" action="{{ route('waste.unsubscribe', $sub) }}" method="POST" class="d-none">
                        @csrf<input type="hidden" name="end_date" value="{{ today()->toDateString() }}">
                    </form>
                @endforeach
            @endcan
        </div>
    </div>
@stop

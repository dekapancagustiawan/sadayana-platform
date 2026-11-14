@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Rekap '.$year)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">Rekap Tahunan {{ $year }}</h1>
        <form method="GET"><input type="number" name="year" value="{{ $year }}" min="2020" max="2100" class="form-control form-control-sm" style="width:100px" onchange="this.form.submit()"></form>
    </div>
@stop

@section('content')
    @php $keys = collect($months)->map(fn ($m) => $m->format('Y-m')); @endphp
    <div class="card"><div class="card-header"><h3 class="card-title">Pemasukan tagihan per jenis (pembayaran terverifikasi)</h3></div>
        <div class="card-body p-0 table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:12px">
            <thead class="thead-light"><tr><th>Jenis</th>@foreach ($months as $m)<th class="text-right">{{ $m->translatedFormat('M') }}</th>@endforeach<th class="text-right">Total</th></tr></thead>
            <tbody>
                @forelse ($byType as $type => $values)
                    <tr><td>{{ \App\Enums\Billing\InvoiceType::tryFrom($type)?->label() ?? $type }}</td>
                        @foreach ($keys as $k)<td class="text-right">{{ isset($values[$k]) ? Money::format($values[$k]) : '' }}</td>@endforeach
                        <td class="text-right font-weight-bold">{{ Money::format(Money::sum($values)) }}</td></tr>
                @empty
                    <tr><td colspan="14" class="text-muted text-center">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="card"><div class="card-header"><h3 class="card-title">Buku kas per kategori</h3></div>
        <div class="card-body p-0 table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:12px">
            <thead class="thead-light"><tr><th>Kategori</th>@foreach ($months as $m)<th class="text-right">{{ $m->translatedFormat('M') }}</th>@endforeach<th class="text-right">Total</th></tr></thead>
            <tbody>
                @forelse ($categories as $row)
                    <tr><td><span class="badge badge-{{ $row['type'] === 'in' ? 'success' : 'danger' }}">{{ $row['type'] === 'in' ? 'masuk' : 'keluar' }}</span> {{ $row['category'] }}</td>
                        @foreach ($keys as $k)<td class="text-right">{{ isset($row['months'][$k]) ? Money::format($row['months'][$k]) : '' }}</td>@endforeach
                        <td class="text-right font-weight-bold">{{ Money::format($row['total']) }}</td></tr>
                @empty
                    <tr><td colspan="14" class="text-muted text-center">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
@stop

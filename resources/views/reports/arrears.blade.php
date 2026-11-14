@extends($print ? 'layouts.print' : 'adminlte::page')

@use('App\Support\Money')

@section('title', 'Tunggakan')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0">Tunggakan</h1>
        <div>
            <a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-print"></i> Cetak</a>
            <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
        </div>
    </div>
@stop

@section('content')
    @if ($print)<h5 class="font-weight-bold">Daftar Tunggakan per {{ today()->translatedFormat('d F Y') }}</h5>@endif
    <p class="small text-muted">Aturan paguyuban: tunggakan {{ $threshold }} bulan berturut-turut → saluran air diputus sementara. Baris merah memenuhi kriteria itu.</p>
    <div class="{{ $print ? '' : 'card' }}"><div class="{{ $print ? '' : 'card-body p-0' }} table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light"><tr><th>Rumah</th><th>Nama</th><th class="text-right">Tagihan terbuka</th><th class="text-right">Bulan lewat jatuh tempo</th><th>Sejak</th><th class="text-right">Total</th>@unless ($print)<th></th>@endunless</tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr @class(['table-danger' => $r['flag']])>
                        <td class="font-weight-bold">{{ $r['house']->house_code }}</td>
                        <td>{{ $r['name'] }}</td>
                        <td class="text-right">{{ $r['invoices'] }}</td>
                        <td class="text-right">{{ $r['overdue_months'] }}</td>
                        <td>{{ $r['oldest']?->translatedFormat('M Y') }}</td>
                        <td class="text-right font-weight-bold">{{ Money::format($r['total']) }}</td>
                        @unless ($print)<td><a href="{{ route('houses.show', $r['house']) }}" class="btn btn-xs btn-outline-secondary">Rumah</a></td>@endunless
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">Tidak ada tunggakan. 🎉</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr class="font-weight-bold"><td colspan="5" class="text-right">Jumlah</td><td class="text-right">{{ Money::format(Money::sum($rows->pluck('total'))) }}</td>@unless ($print)<td></td>@endunless</tr></tfoot>
        </table>
    </div></div>
@stop

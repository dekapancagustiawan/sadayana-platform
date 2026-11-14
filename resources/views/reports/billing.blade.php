@extends($print ? 'layouts.print' : 'adminlte::page')

@use('App\Support\Money')

@section('title', 'Daftar Tagihan '.$month->translatedFormat('F Y'))

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0">Daftar Tagihan {{ $month->translatedFormat('F Y') }}</h1>
        <form method="GET" class="form-inline mt-2 mt-sm-0">
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
            <a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" class="btn btn-outline-primary btn-sm mr-1"><i class="fas fa-print"></i> Cetak</a>
            <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
        </form>
    </div>
@stop

@section('content')
    @if ($print)
        <h5 class="text-center font-weight-bold mb-0">DAFTAR TAGIHAN PEMBAYARAN AIR, SAMPAH & IURAN WARGA</h5>
        <p class="text-center">UNTUK PEMAKAIAN BULAN {{ strtoupper($month->translatedFormat('F Y')) }}</p>
    @endif
    <div class="{{ $print ? '' : 'card' }}"><div class="{{ $print ? '' : 'card-body p-0' }} table-responsive">
        <table class="table table-sm table-bordered table-striped mb-0" style="font-size:12px">
            <thead class="thead-light text-center">
                <tr>
                    <th rowspan="2">No</th><th rowspan="2">Rumah</th><th rowspan="2">Nama</th>
                    <th colspan="3">Angka meter</th><th colspan="2">Air</th><th rowspan="2">Sampah</th>
                    @foreach ($contributionNames as $name)<th rowspan="2">{{ $name }}</th>@endforeach
                    <th rowspan="2">Bulan ini</th><th rowspan="2">Tunggakan</th><th rowspan="2">Jumlah</th><th rowspan="2">Ket.</th>
                </tr>
                <tr><th>Awal</th><th>Akhir</th><th>m³</th><th>Pemakaian</th><th>Abodemen</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $r)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="font-weight-bold">{{ $r['house']->house_code }}</td>
                        <td>{{ $r['name'] }}</td>
                        <td class="text-right">{{ $r['usage'] ? Money::formatQuantity($r['usage']->previous_value) : '' }}</td>
                        <td class="text-right">{{ $r['usage'] ? Money::formatQuantity($r['usage']->current_value) : '' }}</td>
                        <td class="text-right">{{ $r['usage'] ? Money::formatQuantity($r['usage']->usage_volume) : '' }}</td>
                        <td class="text-right">{{ $r['water_usage_amount'] !== null ? Money::format($r['water_usage_amount']) : '' }}</td>
                        <td class="text-right">{{ $r['water_base_fee'] !== null ? Money::format($r['water_base_fee']) : '' }}</td>
                        <td class="text-right">{{ $r['waste'] !== null ? Money::format($r['waste']) : '' }}</td>
                        @foreach ($contributionNames->keys() as $id)<td class="text-right">{{ $r['contributions'][$id] !== null ? Money::format($r['contributions'][$id]) : '' }}</td>@endforeach
                        <td class="text-right">{{ $r['has_invoices'] ? Money::format($r['current']) : '' }}</td>
                        <td class="text-right {{ $r['arrears']->isPositive() ? 'text-danger font-weight-bold' : '' }}">{{ $r['arrears']->isPositive() ? Money::format($r['arrears']) : '' }}</td>
                        <td class="text-right font-weight-bold">{{ $r['grand']->isPositive() ? Money::format($r['grand']) : '' }}</td>
                        <td class="text-center">{{ $r['has_invoices'] ? ($r['paid'] && ! $r['arrears']->isPositive() ? 'Lunas' : '') : 'BM/—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="font-weight-bold">
                <tr>
                    <td colspan="6" class="text-right">Jumlah</td>
                    <td class="text-right">{{ Money::format($totals['water']) }}</td><td class="text-right">{{ Money::format($totals['base']) }}</td><td class="text-right">{{ Money::format($totals['waste']) }}</td>
                    <td colspan="{{ $contributionNames->count() }}" class="text-right">{{ Money::format($totals['contributions']) }}</td>
                    <td class="text-right">{{ Money::format($totals['current']) }}</td><td class="text-right">{{ Money::format($totals['arrears']) }}</td><td class="text-right">{{ Money::format($totals['grand']) }}</td><td></td>
                </tr>
            </tfoot>
        </table>
    </div></div>
    <p class="small text-muted mt-2">Sisa belum dibayar saat ini (termasuk tunggakan): <strong>{{ Money::format($totals['outstanding']) }}</strong>. "BM/—" = belum ada tagihan bulan ini (pembebasan / tidak berlangganan).</p>
@stop

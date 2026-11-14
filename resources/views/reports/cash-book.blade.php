@extends($print ? 'layouts.print' : 'adminlte::page')

@use('App\Support\Money')

@section('title', 'Buku Kas '.$month->translatedFormat('F Y'))

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0">Buku Kas</h1>
        <form method="GET" class="form-inline mt-2 mt-sm-0">
            <select name="account" class="form-control form-control-sm mr-2" onchange="this.form.submit()">@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($a->id === $account->id)>{{ $a->account_name }}</option>@endforeach</select>
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
            <a href="{{ request()->fullUrlWithQuery(['print' => 1, 'account' => $account->id, 'month' => $month->format('Y-m')]) }}" target="_blank" class="btn btn-outline-primary btn-sm mr-1"><i class="fas fa-print"></i> Cetak</a>
            <a href="{{ request()->fullUrlWithQuery(['format' => 'csv', 'account' => $account->id, 'month' => $month->format('Y-m')]) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
        </form>
    </div>
@stop

@section('content')
    <h5 class="text-center font-weight-bold mb-0">BUKU KAS {{ strtoupper($account->account_name) }}</h5>
    <p class="text-center">BULAN {{ strtoupper($month->translatedFormat('F Y')) }}</p>
    <div class="{{ $print ? '' : 'card' }}"><div class="{{ $print ? '' : 'card-body p-0' }} table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light text-center"><tr><th>No</th><th>Tanggal</th><th>Keterangan</th><th>Debet</th><th>Kredit</th><th>Saldo</th></tr></thead>
            <tbody>
                <tr><td class="text-center">1</td><td>{{ $month->format('01-M-y') }}</td><td>Saldo bulan {{ $month->copy()->subMonthNoOverflow()->translatedFormat('F Y') }}</td><td class="text-right">{{ Money::format($book['opening']) }}</td><td></td><td class="text-right">{{ Money::format($book['opening']) }}</td></tr>
                @foreach ($book['rows'] as $i => $row)
                    <tr>
                        <td class="text-center">{{ $i + 2 }}</td>
                        <td>{{ $row['tx']->transaction_date->format('d-M-y') }}</td>
                        <td>{{ $row['tx']->description }}</td>
                        <td class="text-right">{{ $row['tx']->transaction_type === 'in' ? Money::format($row['tx']->amount) : '' }}</td>
                        <td class="text-right">{{ $row['tx']->transaction_type === 'out' ? Money::format($row['tx']->amount) : '' }}</td>
                        <td class="text-right">{{ Money::format($row['balance']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="font-weight-bold">
                <tr><td></td><td>{{ $month->copy()->endOfMonth()->format('d-M-y') }}</td><td>Jumlah</td><td class="text-right">{{ Money::format($book['opening']->plus($book['in'])) }}</td><td class="text-right">{{ Money::format($book['out']) }}</td><td class="text-right">{{ Money::format($book['closing']) }}</td></tr>
            </tfoot>
        </table>
    </div></div>
    @if ($print)
        <div class="d-flex justify-content-around mt-5 text-center">
            <div>Bendahara<br><br><br>(..............................)</div>
            <div>Ketua Paguyuban<br><br><br>(..............................)</div>
        </div>
    @endif
@stop

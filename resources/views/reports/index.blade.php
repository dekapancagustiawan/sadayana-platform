@extends('adminlte::page')

@section('title', 'Laporan')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-chart-bar mr-2 text-primary"></i>Laporan</h1>
@stop

@section('content')
    <div class="row">
        @foreach ([
            ['reports.billing', 'fas fa-list', 'Daftar Tagihan per Rumah', 'Format seperti daftar tagihan bulanan: meter, air, abodemen, sampah, iuran, tunggakan, jumlah.'],
            ['reports.arrears', 'fas fa-exclamation-triangle', 'Tunggakan', 'Rumah yang menunggak, lama tunggakan, dan penanda putus sementara (≥ '.config('billing.disconnect_after_months').' bulan).'],
            ['reports.cash-book', 'fas fa-book', 'Buku Kas', 'Saldo awal, debet, kredit, saldo akhir per bulan per akun kas.'],
            ['reports.recap', 'fas fa-calendar-alt', 'Rekap Tahunan', 'Pemasukan & pengeluaran per kategori dan pemasukan tagihan per jenis, per bulan.'],
        ] as [$route, $icon, $title, $desc])
            <div class="col-md-6">
                <a href="{{ route($route) }}" class="card card-outline card-primary d-block text-dark">
                    <div class="card-body d-flex">
                        <i class="{{ $icon }} fa-2x text-primary mr-3"></i>
                        <div><h5 class="mb-1">{{ $title }}</h5><p class="mb-0 text-muted small">{{ $desc }}</p></div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
    <p class="small text-muted">Setiap laporan bisa dicetak atau diunduh sebagai CSV (dibuka di Excel).</p>
@stop

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte.min.css') }}">
    <style>
        body { background: #fff; color: #111; font-size: 12px; }
        .print-wrap { max-width: 1100px; margin: 0 auto; padding: 16px; }
        table.table-sm td, table.table-sm th { padding: .2rem .35rem; }
        .text-money { text-align: right; white-space: nowrap; }
        @media print { .no-print { display: none !important; } .print-wrap { padding: 0; } @page { margin: 12mm; } }
    </style>
</head>
<body>
<div class="print-wrap">
    <div class="no-print mb-3 d-flex justify-content-between">
        <a href="{{ url()->current().'?'.http_build_query(request()->except('print')) }}" class="btn btn-sm btn-secondary">&larr; Kembali</a>
        <button onclick="window.print()" class="btn btn-sm btn-primary">Cetak</button>
    </div>
    <div class="d-flex align-items-center mb-3">
        <img src="{{ asset('images/sadayana-mark.png') }}" alt="" style="height:42px" class="mr-2">
        <div>
            <div class="font-weight-bold" style="font-size:15px">{{ app(\App\Support\CurrentCommunity::class)->get()->name }}</div>
            <div class="text-muted">{{ config('app.name') }} — Silih Asih, Silih Asah, Silih Asuh</div>
        </div>
    </div>
    @yield('content')
    <div class="text-muted mt-4" style="font-size:10px">Dicetak {{ now()->translatedFormat('d F Y H:i') }}</div>
</div>
</body>
</html>

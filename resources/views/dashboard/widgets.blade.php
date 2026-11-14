{{-- Ringkasan domain Sadayana per hak akses (FR-RPT-001). --}}
@use('App\Support\Money')

@isset($widgets['resident'])
    <div class="row">
        @foreach ($widgets['resident'] as $item)
            <div class="col-md-6">
                <div class="small-box {{ $item['outstanding']->isPositive() ? 'bg-warning' : 'bg-success' }}">
                    <div class="inner">
                        <h3 style="font-size:1.8rem">{{ $item['outstanding']->isPositive() ? Money::format($item['outstanding']) : 'Lunas' }}</h3>
                        <p class="mb-0">Tagihan {{ $item['house']->house_code }}{{ $item['pending'] ? ' · '.$item['pending'].' pembayaran diverifikasi' : '' }}</p>
                    </div>
                    <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    <a href="{{ route('my-bills.index') }}" class="small-box-footer">{{ $item['outstanding']->isPositive() ? 'Bayar sekarang' : 'Lihat riwayat' }} <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        @endforeach
    </div>
@endisset

@isset($widgets['recording'])
    @php
        $r = $widgets['recording'];
    @endphp
    <div class="small-box bg-info">
        <div class="inner"><h3>{{ $r['done'] }} / {{ $r['total'] }}</h3><p class="mb-0">Meter tercatat · {{ $r['period']->translatedFormat('F Y') }}</p></div>
        <div class="icon"><i class="fas fa-camera"></i></div>
        <a href="{{ route('meter-recording.index') }}" class="small-box-footer">Catat meter <i class="fas fa-arrow-circle-right"></i></a>
    </div>
@endisset

<div class="row">
    @isset($widgets['community'])
        <div class="col-lg-3 col-6"><div class="small-box bg-white"><div class="inner"><h3>{{ $widgets['community']['houses'] }}</h3><p class="mb-0">Rumah · {{ $widgets['community']['occupied'] }} dihuni, {{ $widgets['community']['vacant'] }} kosong</p></div><div class="icon"><i class="fas fa-house-user"></i></div></div></div>
    @endisset
    @isset($widgets['billing'])
        <div class="col-lg-3 col-6"><div class="small-box bg-white"><div class="inner"><h3 class="text-danger" style="font-size:1.6rem">{{ Money::format($widgets['billing']['outstanding']) }}</h3><p class="mb-0">Belum dibayar · {{ $widgets['billing']['overdue_houses'] }} rumah lewat jatuh tempo</p></div><div class="icon"><i class="fas fa-exclamation-triangle"></i></div><a href="{{ route('reports.arrears') }}" class="small-box-footer text-dark">Tunggakan <i class="fas fa-arrow-circle-right"></i></a></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-white"><div class="inner"><h3 class="text-success" style="font-size:1.6rem">{{ Money::format($widgets['billing']['paid_this_month']) }}</h3><p class="mb-0">Pembayaran masuk bulan ini</p></div><div class="icon"><i class="fas fa-hand-holding-usd"></i></div></div></div>
    @endisset
    @isset($widgets['cash'])
        <div class="col-lg-3 col-6"><div class="small-box bg-white"><div class="inner"><h3 style="font-size:1.6rem">{{ Money::format($widgets['cash']['balance']) }}</h3><p class="mb-0">Saldo kas</p></div><div class="icon"><i class="fas fa-book"></i></div><a href="{{ route('cash.index') }}" class="small-box-footer text-dark">Buku kas <i class="fas fa-arrow-circle-right"></i></a></div></div>
    @endisset
</div>

@php
    $todo = collect([
        isset($widgets['pending_payments']) && $widgets['pending_payments'] ? [$widgets['pending_payments'].' pembayaran menunggu verifikasi', route('payments.index')] : null,
        isset($widgets['cash']) && $widgets['cash']['pending_expenses'] ? [$widgets['cash']['pending_expenses'].' pengeluaran menunggu persetujuan', route('cash.index')] : null,
        isset($widgets['pending_readings']) && $widgets['pending_readings'] ? [$widgets['pending_readings'].' pembacaan meter janggal', route('meter-readings.index', ['status' => 'pending'])] : null,
        isset($widgets['billing']) && $widgets['billing']['drafts'] ? [$widgets['billing']['drafts'].' draft tagihan belum diterbitkan', route('invoices.index')] : null,
        isset($widgets['open_complaints']) && $widgets['open_complaints'] ? [$widgets['open_complaints'].' pengaduan belum selesai', route('complaints.index')] : null,
    ])->filter();
@endphp

<div class="row">
    @if ($todo->isNotEmpty())
        <div class="col-lg-6">
            <div class="card card-outline card-warning"><div class="card-header"><h3 class="card-title"><i class="fas fa-tasks mr-1"></i> Perlu tindakan</h3></div>
                <ul class="list-group list-group-flush">@foreach ($todo as [$label, $url])<li class="list-group-item"><a href="{{ $url }}">{{ $label }}</a></li>@endforeach</ul>
            </div>
        </div>
    @endif
    @if (! empty($widgets['announcements']) && $widgets['announcements']->isNotEmpty())
        <div class="col-lg-6">
            <div class="card card-outline card-primary"><div class="card-header"><h3 class="card-title"><i class="fas fa-bullhorn mr-1"></i> Pengumuman</h3></div>
                <ul class="list-group list-group-flush">@foreach ($widgets['announcements'] as $a)<li class="list-group-item"><a href="{{ route('announcements.show', $a) }}">{{ $a->title }}</a> <small class="text-muted">· {{ $a->publish_at->translatedFormat('d M') }}</small></li>@endforeach</ul>
            </div>
        </div>
    @endif
    @if ($widgets['unread']->isNotEmpty())
        <div class="col-lg-6">
            <div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-bell mr-1"></i> Notifikasi baru</h3><div class="card-tools"><a href="{{ route('notifications.index') }}" class="btn btn-tool">Semua</a></div></div>
                <ul class="list-group list-group-flush">@foreach ($widgets['unread'] as $n)<li class="list-group-item small"><a href="{{ route('notifications.open', $n->id) }}"><strong>{{ $n->data['title'] ?? '' }}</strong></a> — {{ $n->data['message'] ?? '' }}</li>@endforeach</ul>
            </div>
        </div>
    @endif
</div>

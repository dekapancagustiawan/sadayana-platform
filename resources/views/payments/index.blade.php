@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Pembayaran')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-money-check-alt mr-2 text-primary"></i>Pembayaran</h1>
        @can('payments.verify')
            <a href="{{ route('payments.cash') }}" class="btn btn-success btn-sm"><i class="fas fa-hand-holding-usd mr-1"></i> Terima pembayaran tunai</a>
        @endcan
    </div>
@stop

@section('content')
    @include('partials.flash')

    <div class="mb-3">
        <a href="{{ route('payments.index') }}" class="btn btn-sm {{ $status === 'pending_verification' ? 'btn-warning' : 'btn-outline-warning' }}">Menunggu verifikasi ({{ $pendingCount }})</a>
        @foreach ($statuses as $s)
            @continue($s->value === 'pending_verification')
            <a href="{{ route('payments.index', ['status' => $s->value]) }}" class="btn btn-sm {{ $status === $s->value ? 'btn-secondary' : 'btn-outline-secondary' }}">{{ $s->label() }}</a>
        @endforeach
        <a href="{{ route('payments.index', ['status' => 'all']) }}" class="btn btn-sm {{ $status === 'all' ? 'btn-secondary' : 'btn-outline-secondary' }}">Semua</a>
    </div>

    <div class="card"><div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-light"><tr><th>No.</th><th>Rumah</th><th>Tanggal</th><th>Metode</th><th class="text-right">Jumlah</th><th>Status</th><th>Pengaju</th></tr></thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td><a href="{{ route('payments.show', $payment) }}">{{ $payment->payment_number }}</a></td>
                        <td class="font-weight-bold">{{ $payment->house->house_code }}</td>
                        <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                        <td>{{ $payment->payment_method === 'cash' ? 'Tunai' : 'Transfer' }}</td>
                        <td class="text-right">{{ Money::format($payment->amount) }}</td>
                        <td><span class="badge badge-{{ $payment->status->badge() }}">{{ $payment->status->label() }}</span></td>
                        <td class="small text-muted">{{ $payment->payer?->name ?? 'Bendahara' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada pembayaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($payments->hasPages())<div class="card-footer">{{ $payments->links('partials.pagination') }}</div>@endif
    </div>
@stop

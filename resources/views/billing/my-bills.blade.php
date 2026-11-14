@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Tagihan Saya')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Tagihan Saya</h1>
@stop

@section('content')
    @include('partials.flash')

    @forelse ($items as $item)
        @php
            $house = $item['house'];
            $payable = $item['payable'];
            $outstanding = Money::sum($payable->pluck('outstanding_amount'));
        @endphp

        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">{{ $house->house_code }}</h3>
                <div class="card-tools">
                    @if ($outstanding->isPositive())
                        <span class="badge badge-danger" style="font-size:.95rem">Belum dibayar {{ Money::format($outstanding) }}</span>
                    @else
                        <span class="badge badge-success" style="font-size:.95rem">Tidak ada tagihan terbuka</span>
                    @endif
                </div>
            </div>

            @if ($payable->isNotEmpty())
                <form action="{{ route('my-bills.pay', $house) }}" method="POST" enctype="multipart/form-data" class="pay-form" data-house="{{ $house->id }}">@csrf
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="thead-light"><tr><th style="width:30px"></th><th>Tagihan</th><th>Periode</th><th>Jatuh tempo</th><th class="text-right">Jumlah</th></tr></thead>
                            <tbody>
                                @foreach ($payable as $invoice)
                                    <tr>
                                        <td><input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" data-amount="{{ $invoice->outstanding_amount }}" checked class="pay-pick"></td>
                                        <td><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->title() }}</a></td>
                                        <td>{{ $invoice->period->label() }}</td>
                                        <td>{{ $invoice->due_date?->format('d/m/Y') }} @if ($invoice->status->value === 'overdue')<span class="badge badge-danger">lewat</span>@endif</td>
                                        <td class="text-right">{{ Money::format($invoice->outstanding_amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @can('payments.submit')
                        <div class="card-body border-top">
                            <h6 class="font-weight-bold">Bayar via transfer ke bendahara</h6>
                            <p class="small text-muted mb-2">Centang tagihan yang dibayar, transfer <strong class="pay-total">{{ Money::format($outstanding) }}</strong> dalam satu kali transfer, lalu unggah buktinya. Tagihan lunas setelah diverifikasi bendahara. Bayar tunai? Serahkan langsung ke bendahara.</p>
                            <div class="form-row">
                                <div class="form-group col-sm-4"><label class="small">Tanggal transfer</label><input type="date" name="payment_date" class="form-control" required max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}"></div>
                                <div class="form-group col-sm-4"><label class="small">No. referensi (opsional)</label><input name="external_reference" class="form-control" maxlength="100"></div>
                                <div class="form-group col-sm-4"><label class="small">Bukti transfer</label><input type="file" name="proof" accept="image/*,application/pdf" class="form-control-file" required></div>
                            </div>
                            <button class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i> Kirim bukti pembayaran</button>
                        </div>
                    @endcan
                </form>
            @endif

            @php $open = $item['payments']->filter(fn ($p) => $p->status->isOpen()); @endphp
            @foreach ($open as $payment)
                <div class="card-body border-top bg-light">
                    <strong>{{ $payment->payment_number }}</strong> · {{ Money::format($payment->amount) }}
                    <span class="badge badge-{{ $payment->status->badge() }}">{{ $payment->status->label() }}</span>
                    <div class="small text-muted">{{ $payment->allocations->map(fn ($a) => $a->invoice->title().' '.$a->invoice->period?->label())->join(', ') }}</div>
                    @if ($payment->status->value === 'correction_required')
                        <div class="alert alert-info small py-1 my-2">Catatan bendahara: {{ $payment->rejection_reason }}</div>
                        <form action="{{ route('my-bills.resubmit', $payment) }}" method="POST" enctype="multipart/form-data" class="form-inline">@csrf
                            <input type="file" name="proof" accept="image/*,application/pdf" required class="form-control-file mr-2 mb-1" style="max-width:240px">
                            <input name="external_reference" class="form-control form-control-sm mr-2 mb-1" placeholder="No. referensi">
                            <button class="btn btn-sm btn-info mb-1">Kirim ulang bukti</button>
                        </form>
                    @endif
                    <form action="{{ route('my-bills.cancel', $payment) }}" method="POST" class="d-inline">@csrf
                        <button class="btn btn-link btn-sm p-0 text-danger" onclick="return confirm('Batalkan pengajuan ini?')">Batalkan pengajuan</button>
                    </form>
                </div>
            @endforeach

            <div class="card-body border-top">
                <h6 class="text-muted text-uppercase small">Riwayat tagihan</h6>
                <div class="table-responsive">
                    <table class="table table-sm small mb-0">
                        <thead><tr><th>Periode</th><th>Tagihan</th><th class="text-right">Jumlah</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($item['invoices'] as $invoice)
                                <tr>
                                    <td>{{ $invoice->period->label() }}</td>
                                    <td><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->title() }}</a></td>
                                    <td class="text-right">{{ Money::format($invoice->total_amount) }}</td>
                                    <td><span class="badge badge-{{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Belum ada tagihan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="callout callout-info"><h5>Akun Anda belum terhubung ke rumah</h5><p class="mb-0">Hubungi pengurus untuk menghubungkan akun ini dengan data penghuni.</p></div>
    @endforelse
@stop

@push('js')
<script>
    document.querySelectorAll('.pay-form').forEach(function (form) {
        const total = form.querySelector('.pay-total');
        const update = function () {
            let sum = 0;
            form.querySelectorAll('.pay-pick:checked').forEach(function (c) { sum += parseFloat(c.dataset.amount); });
            if (total) total.textContent = 'Rp' + sum.toLocaleString('id-ID');
        };
        form.querySelectorAll('.pay-pick').forEach(function (c) { c.addEventListener('change', update); });
    });
</script>
@endpush

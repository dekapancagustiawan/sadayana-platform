@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Pembayaran '.$payment->payment_number)

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-money-check-alt mr-2 text-primary"></i>{{ $payment->payment_number }}
            <span class="badge badge-{{ $payment->status->badge() }} align-middle" style="font-size:.9rem">{{ $payment->status->label() }}</span></h1>
        <a href="{{ route('payments.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Daftar</a>
    </div>
@stop

@section('content')
    @include('partials.flash')
    <div class="row">
        <div class="col-lg-5">
            <div class="card"><div class="card-body text-center">
                @if ($payment->proof_file_id)
                    <a href="{{ route('files.show', $payment->proof_file_id) }}" target="_blank" rel="noopener">
                        <img src="{{ route('files.show', $payment->proof_file_id) }}" alt="Bukti transfer" class="img-fluid rounded" style="max-height:420px" onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'Buka bukti (PDF)'}))">
                    </a>
                @else
                    <p class="text-muted my-4">{{ $payment->payment_method === 'cash' ? 'Pembayaran tunai — diterima langsung bendahara.' : 'Tanpa bukti.' }}</p>
                @endif
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card"><div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Rumah</dt><dd class="col-7"><a href="{{ route('houses.show', $payment->house) }}">{{ $payment->house->house_code }}</a></dd>
                    <dt class="col-5">Jumlah</dt><dd class="col-7 h5">{{ Money::format($payment->amount) }}</dd>
                    <dt class="col-5">Tanggal / metode</dt><dd class="col-7">{{ $payment->payment_date->format('d/m/Y') }} · {{ $payment->payment_method === 'cash' ? 'Tunai' : 'Transfer' }}</dd>
                    @if ($payment->external_reference)<dt class="col-5">Referensi</dt><dd class="col-7">{{ $payment->external_reference }}</dd>@endif
                    <dt class="col-5">Pengaju</dt><dd class="col-7">{{ $payment->payer?->name ?? 'Bendahara (tunai)' }}</dd>
                    @if ($payment->notes)<dt class="col-5">Catatan</dt><dd class="col-7">{{ $payment->notes }}</dd>@endif
                    @if ($payment->cashTransaction)<dt class="col-5">Buku kas</dt><dd class="col-7">{{ $payment->cashTransaction->transaction_number }}</dd>@endif
                </dl>
                <table class="table table-sm mt-3 mb-0">
                    <thead class="thead-light"><tr><th>Tagihan</th><th>Periode</th><th class="text-right">Dialokasikan</th></tr></thead>
                    <tbody>
                        @foreach ($payment->allocations as $allocation)
                            <tr><td><a href="{{ route('invoices.show', $allocation->invoice) }}">{{ $allocation->invoice->title() }}</a> <span class="text-muted small">{{ $allocation->invoice->invoice_number }}</span></td>
                                <td>{{ $allocation->invoice->period->label() }}</td>
                                <td class="text-right">{{ Money::format($allocation->allocated_amount) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>

            @if ($payment->status->value === 'pending_verification')
                @can('payments.verify')
                    <div class="card card-outline card-success"><div class="card-body">
                        <form action="{{ route('payments.verify', $payment) }}" method="POST" class="mb-3">@csrf
                            <div class="form-row">
                                <div class="col-sm-5"><select name="cash_account_id" class="form-control form-control-sm">@foreach ($accounts as $account)<option value="{{ $account->id }}">Masuk ke: {{ $account->account_name }}</option>@endforeach</select></div>
                                <div class="col-sm-7"><input name="notes" class="form-control form-control-sm" placeholder="Catatan (opsional)"></div>
                            </div>
                            <button class="btn btn-success mt-2"><i class="fas fa-check mr-1"></i> Verifikasi — tagihan lunas & masuk kas</button>
                        </form>
                        <form action="{{ route('payments.reject', $payment) }}" method="POST">@csrf
                            <input name="reason" class="form-control form-control-sm mb-2" required maxlength="500" placeholder="Alasan (wajib), mis. nominal transfer tidak sesuai">
                            <button name="action" value="correction" class="btn btn-outline-info btn-sm">Minta perbaikan bukti</button>
                            <button name="action" value="reject" class="btn btn-outline-danger btn-sm">Tolak</button>
                        </form>
                    </div></div>
                @endcan
            @elseif ($payment->status->value === 'verified')
                @can('payments.reverse')
                    <form action="{{ route('payments.reverse', $payment) }}" method="POST" class="card collapsed-card">@csrf
                        <div class="card-header"><h3 class="card-title text-danger">Batalkan pembayaran (koreksi)</h3>
                            <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div></div>
                        <div class="card-body">
                            <p class="small text-muted">Tagihan kembali terbuka dan penerimaan kas dibalik dengan transaksi pembalik (tercatat di audit).</p>
                            <input name="reason" class="form-control form-control-sm mb-2" required maxlength="500" placeholder="Alasan">
                            <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Batalkan pembayaran yang sudah diverifikasi?')">Batalkan pembayaran</button>
                        </div>
                    </form>
                @endcan
            @endif

            @if ($payment->verifications->isNotEmpty())
                <div class="card"><div class="card-header"><h3 class="card-title">Riwayat pemeriksaan</h3></div>
                    <ul class="list-group list-group-flush small">
                        @foreach ($payment->verifications as $v)
                            <li class="list-group-item">{{ $v->verified_at->format('d/m/Y H:i') }} · {{ $v->verifier?->name }} · <strong>{{ $v->verification_status }}</strong> {{ $v->notes ? '— '.$v->notes : '' }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@stop

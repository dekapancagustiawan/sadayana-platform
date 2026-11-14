@extends($print ? 'layouts.print' : 'adminlte::page')

@section('title', 'Tagihan '.$invoice->invoice_number)

@if ($print)
    @section('content')
        @include('billing.invoices._document')
    @endsection
@else
    @section('content_header')
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <h1 class="m-0"><i class="fas fa-file-invoice mr-2 text-primary"></i>{{ $invoice->invoice_number }}
                <span class="badge badge-{{ $invoice->status->badge() }} align-middle" style="font-size:.9rem">{{ $invoice->status->label() }}</span></h1>
            <div class="mt-2 mt-sm-0">
                <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                <a href="{{ route('invoices.show', [$invoice, 'print' => 1]) }}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-print mr-1"></i> Cetak</a>
            </div>
        </div>
    @stop

    @section('content')
        @include('partials.flash')
        <div class="row">
            <div class="col-lg-8"><div class="card"><div class="card-body">@include('billing.invoices._document')</div></div></div>
            <div class="col-lg-4">
                @if ($invoice->allocations->isNotEmpty())
                    <div class="card"><div class="card-header"><h3 class="card-title">Pembayaran</h3></div>
                        <ul class="list-group list-group-flush">
                            @foreach ($invoice->allocations as $allocation)
                                <li class="list-group-item small">
                                    @can('payments.view')<a href="{{ route('payments.show', $allocation->payment) }}">{{ $allocation->payment->payment_number }}</a>@else{{ $allocation->payment->payment_number }}@endcan
                                    · {{ $allocation->payment->payment_date->format('d/m/Y') }}
                                    <span class="badge badge-{{ $allocation->payment->status->badge() }}">{{ $allocation->payment->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($invoice->status->value === 'cancelled')
                    <div class="callout callout-dark small">Dibatalkan {{ $invoice->cancelled_at?->format('d/m/Y') }}: {{ $invoice->cancellation_reason }}</div>
                @elseif ((float) $invoice->paid_amount == 0)
                    @can('invoices.cancel')
                        <form action="{{ route('invoices.cancel', $invoice) }}" method="POST" class="card card-outline card-danger">@csrf
                            <div class="card-body">
                                <label class="small">Batalkan tagihan (mis. salah hitung)</label>
                                <input name="reason" class="form-control form-control-sm mb-2" required maxlength="500" placeholder="Alasan">
                                <button class="btn btn-outline-danger btn-sm btn-block" onclick="return confirm('Batalkan tagihan ini?')">Batalkan</button>
                            </div>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    @stop
@endif

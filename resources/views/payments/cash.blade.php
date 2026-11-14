@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Terima Pembayaran Tunai')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-hand-holding-usd mr-2 text-success"></i>Terima Pembayaran Tunai</h1>
@stop

@section('content')
    @include('partials.flash')

    <form method="GET" class="mb-3">
        <label class="small" for="house">Rumah</label>
        <select id="house" name="house" class="form-control" onchange="this.form.submit()" style="max-width:320px">
            <option value="">— pilih rumah —</option>
            @foreach ($houses as $h)<option value="{{ $h->id }}" @selected($house?->id === $h->id)>{{ $h->house_code }}</option>@endforeach
        </select>
    </form>

    @if ($house)
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title">{{ $house->house_code }} · {{ $resident?->full_name ?? '—' }}</h3></div>
            @if ($invoices->isEmpty())
                <div class="card-body text-muted">Tidak ada tagihan terbuka untuk rumah ini.</div>
            @else
                <form action="{{ route('payments.cash.store') }}" method="POST">@csrf
                    <input type="hidden" name="house_id" value="{{ $house->id }}">
                    <div class="card-body p-0"><table class="table table-sm mb-0">
                        <thead class="thead-light"><tr><th style="width:30px"></th><th>Tagihan</th><th>Periode</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr><td><input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" checked></td>
                                    <td>{{ $invoice->title() }}</td><td>{{ $invoice->period->label() }}</td>
                                    <td class="text-right">{{ Money::format($invoice->outstanding_amount) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th colspan="3" class="text-right">Total semua</th><th class="text-right">{{ Money::format(Money::sum($invoices->pluck('outstanding_amount'))) }}</th></tr></tfoot>
                    </table></div>
                    <div class="card-footer">
                        <div class="form-row">
                            <div class="col-sm-3"><input type="date" name="payment_date" class="form-control form-control-sm" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                            <div class="col-sm-4"><select name="cash_account_id" class="form-control form-control-sm">@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select></div>
                            <div class="col-sm-5"><input name="notes" class="form-control form-control-sm" placeholder="Catatan (opsional)"></div>
                        </div>
                        <button class="btn btn-success mt-2"><i class="fas fa-check mr-1"></i> Terima tunai — lunas & catat ke kas</button>
                    </div>
                </form>
            @endif
        </div>
    @endif
@stop

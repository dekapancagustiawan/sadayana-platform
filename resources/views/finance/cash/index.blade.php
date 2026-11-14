@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Buku Kas')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-book mr-2 text-primary"></i>Buku Kas</h1>
        <a href="{{ route('reports.cash-book', ['account' => $account->id, 'month' => $month->format('Y-m'), 'print' => 1]) }}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-print mr-1"></i> Cetak buku kas</a>
    </div>
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        @foreach ($accounts as $acc)
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route('cash.index', ['account' => $acc->id, 'month' => $month->format('Y-m')]) }}" class="small-box {{ $acc->id === $account->id ? 'bg-primary' : 'bg-white text-dark' }} d-block">
                    <div class="inner"><h4>{{ Money::format($balances[$acc->id]) }}</h4><p class="mb-0">{{ $acc->account_name }} <small>({{ $acc->account_type === 'bank' ? 'rekening' : 'tunai' }})</small></p></div>
                </a>
            </div>
        @endforeach
    </div>

    @if ($pending->isNotEmpty())
        <div class="card card-outline card-warning">
            <div class="card-header"><h3 class="card-title">Pengeluaran menunggu persetujuan Ketua RT ({{ $pending->count() }})</h3></div>
            <div class="card-body p-0 table-responsive"><table class="table table-sm mb-0">
                @foreach ($pending as $tx)
                    <tr>
                        <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                        <td>{{ $tx->description }} <span class="text-muted small">· {{ $tx->category->name }} · {{ $tx->account->account_name }} · dicatat {{ $tx->recorder?->name }}</span></td>
                        <td class="text-right font-weight-bold">{{ Money::format($tx->amount) }}</td>
                        <td class="text-right text-nowrap">
                            @if ($tx->evidence_file_id)<a href="{{ route('files.show', $tx->evidence_file_id) }}" target="_blank" class="btn btn-xs btn-outline-secondary"><i class="fas fa-paperclip"></i></a>@endif
                            @can('cash.approve')
                                <form action="{{ route('cash.approve', $tx) }}" method="POST" class="d-inline">@csrf<button class="btn btn-xs btn-success">Setujui</button></form>
                                <form action="{{ route('cash.reject', $tx) }}" method="POST" class="d-inline" onsubmit="const r = prompt('Alasan menolak:'); if (!r) return false; this.reason.value = r;">@csrf<input type="hidden" name="reason"><button class="btn btn-xs btn-outline-danger">Tolak</button></form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </table></div>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <form method="GET" class="form-inline">
                        <input type="hidden" name="account" value="{{ $account->id }}">
                        <h3 class="card-title mr-3">{{ $account->account_name }}</h3>
                        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm" onchange="this.form.submit()">
                    </form>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light"><tr><th>Tanggal</th><th>Keterangan</th><th class="text-right">Debet</th><th class="text-right">Kredit</th><th class="text-right">Saldo</th><th></th></tr></thead>
                        <tbody>
                            <tr class="table-light"><td>{{ $month->format('01/m/Y') }}</td><td><em>Saldo awal {{ $month->translatedFormat('F Y') }}</em></td><td></td><td></td><td class="text-right font-weight-bold">{{ Money::format($opening) }}</td><td></td></tr>
                            @foreach ($rows as $row)
                                @php $tx = $row['tx']; @endphp
                                <tr @class(['text-muted' => $tx->status->value !== 'posted'])>
                                    <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                    <td>{{ $tx->description }}
                                        <div class="small text-muted">{{ $tx->transaction_number }} · {{ $tx->category->name }}{{ $tx->counterparty ? ' · '.$tx->counterparty : '' }}
                                            @if ($tx->status->value !== 'posted') · <span class="badge badge-{{ $tx->status->badge() }}">{{ $tx->status->label() }}</span>@endif</div></td>
                                    <td class="text-right">{{ $tx->transaction_type === 'in' ? Money::format($tx->amount) : '' }}</td>
                                    <td class="text-right">{{ $tx->transaction_type === 'out' ? Money::format($tx->amount) : '' }}</td>
                                    <td class="text-right">{{ $tx->status->value === 'posted' ? Money::format($row['balance']) : '' }}</td>
                                    <td class="text-right text-nowrap">
                                        @if ($tx->evidence_file_id)<a href="{{ route('files.show', $tx->evidence_file_id) }}" target="_blank" title="Bukti"><i class="fas fa-paperclip"></i></a>@endif
                                        @if ($tx->status->value === 'posted' && ! $tx->reversal_of_id && ! $tx->payment_id)
                                            @can('cash.reverse')
                                                <form action="{{ route('cash.reverse', $tx) }}" method="POST" class="d-inline" onsubmit="const r = prompt('Alasan pembalikan:'); if (!r) return false; this.reason.value = r;">@csrf<input type="hidden" name="reason"><button class="btn btn-link btn-sm p-0 text-danger" title="Balik transaksi"><i class="fas fa-undo"></i></button></form>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="font-weight-bold">
                            <tr><td></td><td>Jumlah</td><td class="text-right">{{ Money::format($totalIn) }}</td><td class="text-right">{{ Money::format($totalOut) }}</td><td class="text-right">{{ Money::format($closing) }}</td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        @can('cash.record')
            <div class="col-lg-4">
                <form action="{{ route('cash.store') }}" method="POST" enctype="multipart/form-data" class="card card-outline card-primary">@csrf
                    <div class="card-header"><h3 class="card-title">Catat transaksi</h3></div>
                    <div class="card-body">
                        <div class="btn-group btn-group-toggle w-100 mb-2" data-toggle="buttons">
                            <label class="btn btn-outline-success active"><input type="radio" name="transaction_type" value="in" checked onchange="cashType('in')"> Pemasukan</label>
                            <label class="btn btn-outline-danger"><input type="radio" name="transaction_type" value="out" onchange="cashType('out')"> Pengeluaran</label>
                        </div>
                        <input type="hidden" name="cash_account_id" value="{{ $account->id }}">
                        <select name="finance_category_id" class="form-control form-control-sm mb-2" required id="cash-category">
                            @foreach (['in' => 'Pemasukan', 'out' => 'Pengeluaran'] as $type => $label)
                                @foreach ($categories[$type] ?? [] as $category)<option value="{{ $category->id }}" data-type="{{ $type }}">{{ $category->name }}</option>@endforeach
                            @endforeach
                        </select>
                        <input type="date" name="transaction_date" class="form-control form-control-sm mb-2" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                        <input type="number" name="amount" class="form-control form-control-sm mb-2" min="1" required placeholder="Nominal (Rp)">
                        <input name="description" class="form-control form-control-sm mb-2" required maxlength="500" placeholder="Keterangan, mis. 2 tambang">
                        <input name="counterparty" class="form-control form-control-sm mb-2" maxlength="150" placeholder="Dari / kepada (opsional)">
                        <input type="file" name="evidence" accept="image/*,application/pdf" class="form-control-file small">
                        <small class="text-muted d-block mt-2">Pengeluaran tercatat setelah disetujui Ketua RT.</small>
                    </div>
                    <div class="card-footer"><button class="btn btn-primary btn-block">Simpan</button></div>
                </form>

                @can('cash-accounts.manage')
                    <form action="{{ route('cash.accounts.store') }}" method="POST" class="card collapsed-card">@csrf
                        <div class="card-header"><h3 class="card-title">Akun kas baru</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div></div>
                        <div class="card-body">
                            <input name="account_name" class="form-control form-control-sm mb-2" required placeholder="Nama, mis. Rekening BCA Paguyuban">
                            <input name="account_code" class="form-control form-control-sm mb-2" required placeholder="Kode, mis. BCA">
                            <select name="account_type" class="form-control form-control-sm mb-2"><option value="bank">Rekening bank</option><option value="cash">Tunai</option></select>
                            <input type="number" name="opening_balance" class="form-control form-control-sm mb-2" min="0" value="0" required>
                            <input type="date" name="opening_date" class="form-control form-control-sm mb-2" value="{{ today()->startOfMonth()->toDateString() }}" required>
                            <button class="btn btn-outline-primary btn-sm btn-block">Buat akun</button>
                        </div>
                    </form>
                @endcan
            </div>
        @endcan
    </div>
@stop

@push('js')
<script>
    function cashType(type) {
        const select = document.getElementById('cash-category');
        let first = null;
        select.querySelectorAll('option').forEach(function (o) {
            const show = o.dataset.type === type;
            o.hidden = !show; o.disabled = !show;
            if (show && !first) first = o;
        });
        if (first) select.value = first.value;
    }
    document.addEventListener('DOMContentLoaded', function () { cashType('in'); });
</script>
@endpush

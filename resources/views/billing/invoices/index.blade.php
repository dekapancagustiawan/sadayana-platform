@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Tagihan')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-file-invoice mr-2 text-primary"></i>Tagihan {{ $month->translatedFormat('F Y') }}</h1>
        <div class="mt-2 mt-sm-0">
            <a href="{{ route('billing-exemptions.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-user-clock mr-1"></i> Pembebasan (BM)</a>
            <a href="{{ route('reports.billing', ['month' => $month->format('Y-m')]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-list mr-1"></i> Daftar tagihan per rumah</a>
        </div>
    </div>
@stop

@section('content')
    @include('partials.flash')

    @php
        $draft = $totals['draft'] ?? null;
        $sumTotal = Money::sum($totals->except('draft')->pluck('total'));
        $sumOut = Money::sum($totals->except('draft')->pluck('outstanding'));
    @endphp

    <div class="row">
        <div class="col-lg-4">
            <form method="GET" class="card"><div class="card-body">
                <label class="small">Bulan tagihan</label>
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control mb-2" onchange="this.form.submit()">
                <div class="form-row">
                    <div class="col"><select name="type" class="form-control form-control-sm" onchange="this.form.submit()"><option value="">Semua jenis</option>@foreach ($types as $t)<option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ $t->label() }}</option>@endforeach</select></div>
                    <div class="col"><select name="status" class="form-control form-control-sm" onchange="this.form.submit()"><option value="">Semua status</option>@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
                </div>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm mt-2" placeholder="Cari rumah (J-12)…">
            </div></form>

            @can('invoices.generate')
                <div class="card card-outline card-primary">
                    <div class="card-header"><h3 class="card-title">1. Buat draft tagihan</h3></div>
                    <form action="{{ route('invoices.generate') }}" method="POST" class="card-body">@csrf
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        <p class="small text-muted mb-2">Air: dari pembacaan meter {{ $month->translatedFormat('F') }} (terbit awal bulan berikutnya). Sampah & iuran: terbit akhir {{ $month->translatedFormat('F') }}. Aman diulang — tagihan yang sudah ada tidak digandakan.</p>
                        @foreach (['water' => 'Air', 'waste' => 'Sampah', 'contribution' => 'Iuran warga'] as $value => $label)
                            <div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="type-{{ $value }}" name="types[]" value="{{ $value }}" checked><label class="custom-control-label" for="type-{{ $value }}">{{ $label }}</label></div>
                        @endforeach
                        <button class="btn btn-primary btn-sm btn-block mt-2"><i class="fas fa-cogs mr-1"></i> Buat draft {{ $month->translatedFormat('F Y') }}</button>
                    </form>
                </div>
            @endcan

            @if ($draft)
                @can('invoices.issue')
                    <div class="card card-outline card-success">
                        <div class="card-header"><h3 class="card-title">2. Terbitkan</h3></div>
                        <form action="{{ route('invoices.issue') }}" method="POST" class="card-body" onsubmit="return confirm('Terbitkan {{ $draft->count }} tagihan? Warga akan menerima notifikasi.')">@csrf
                            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                            <p class="mb-2"><strong>{{ $draft->count }}</strong> draft senilai <strong>{{ Money::format($draft->total) }}</strong> belum diterbitkan.</p>
                            <button class="btn btn-success btn-sm btn-block"><i class="fas fa-paper-plane mr-1"></i> Terbitkan semua draft</button>
                        </form>
                    </div>
                @endcan
            @endif
        </div>

        <div class="col-lg-8">
            <div class="row">
                <div class="col-sm-4"><div class="small-box bg-white"><div class="inner"><h4>{{ Money::format($sumTotal) }}</h4><p class="mb-0">Total terbit</p></div></div></div>
                <div class="col-sm-4"><div class="small-box bg-white"><div class="inner"><h4 class="text-success">{{ Money::format($sumTotal->minus($sumOut)) }}</h4><p class="mb-0">Sudah dibayar</p></div></div></div>
                <div class="col-sm-4"><div class="small-box bg-white"><div class="inner"><h4 class="text-danger">{{ Money::format($sumOut) }}</h4><p class="mb-0">Belum dibayar</p></div></div></div>
            </div>

            <div class="card">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light"><tr><th>Rumah</th><th>Jenis</th><th>No.</th><th>Jatuh tempo</th><th class="text-right">Total</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td class="font-weight-bold">{{ $invoice->house->house_code }}</td>
                                    <td><i class="{{ $invoice->invoice_type->icon() }} text-muted mr-1"></i>{{ $invoice->title() }}</td>
                                    <td><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                                    <td>{{ $invoice->due_date?->format('d/m/Y') }}</td>
                                    <td class="text-right">{{ Money::format($invoice->total_amount) }}</td>
                                    <td><span class="badge badge-{{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada tagihan untuk {{ $month->translatedFormat('F Y') }}.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($invoices->hasPages())<div class="card-footer">{{ $invoices->links('partials.pagination') }}</div>@endif
            </div>
        </div>
    </div>
@stop

@use('App\Support\Money')
<div class="d-flex justify-content-between mb-3">
    <div>
        <div class="text-muted small">Tagihan {{ $invoice->title() }}</div>
        <div class="h4 mb-0">{{ $invoice->invoice_number }}</div>
        <div>Periode <strong>{{ $invoice->period->label() }}</strong></div>
    </div>
    <div class="text-right">
        <div class="h5 mb-0">{{ $invoice->house->house_code }}</div>
        <div>{{ $resident?->full_name ?? '—' }}</div>
        <div class="small text-muted">Terbit {{ $invoice->invoice_date->format('d/m/Y') }} · Jatuh tempo {{ $invoice->due_date?->format('d/m/Y') }}</div>
    </div>
</div>
<table class="table table-sm table-bordered">
    <thead class="thead-light"><tr><th>Rincian</th><th class="text-right">Jumlah</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>
    <tbody>
        @foreach ($invoice->details as $detail)
            <tr>
                <td>{{ $detail->description }}</td>
                <td class="text-right">{{ Money::formatQuantity($detail->quantity) }}</td>
                <td class="text-right">{{ Money::format($detail->unit_price) }}</td>
                <td class="text-right">{{ Money::format($detail->subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th colspan="3" class="text-right">Total</th><th class="text-right">{{ Money::format($invoice->total_amount) }}</th></tr>
        <tr><th colspan="3" class="text-right">Sudah dibayar</th><th class="text-right">{{ Money::format($invoice->paid_amount) }}</th></tr>
        <tr><th colspan="3" class="text-right">Sisa</th><th class="text-right">{{ Money::format($invoice->outstanding_amount) }}</th></tr>
    </tfoot>
</table>
<p class="mb-0">Status: <strong>{{ $invoice->status->label() }}</strong>@if ($invoice->paid_at) · lunas {{ $invoice->paid_at->format('d/m/Y') }}@endif</p>

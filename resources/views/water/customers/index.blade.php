@extends('adminlte::page')

@use('App\Support\Money')

@section('title', 'Pelanggan Air')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-faucet mr-2 text-primary"></i>Pelanggan Air</h1>
@stop

@section('content')
    @include('partials.flash')

    <div class="callout callout-info small">
        Rumah didaftarkan sebagai pelanggan air dari halaman <a href="{{ route('houses.index') }}">detail rumah</a> (kartu <em>Air</em>). Tidak semua rumah wajib berlangganan.
    </div>

    <div class="d-flex flex-wrap mb-3">
        <a href="{{ route('water-customers.index') }}" class="btn btn-sm mr-2 mb-2 {{ request('status') ? 'btn-outline-secondary' : 'btn-secondary' }}">Semua aktif ({{ $counts->sum() }})</a>
        @foreach ($statuses as $status)
            @if ($status->value !== 'inactive')
                <a href="{{ route('water-customers.index', ['status' => $status->value]) }}" class="btn btn-sm mr-2 mb-2 {{ request('status') === $status->value ? 'btn-'.$status->badge() : 'btn-outline-'.$status->badge() }}">
                    {{ $status->label() }} ({{ $counts[$status->value] ?? 0 }})
                </a>
            @endif
        @endforeach
        <a href="{{ route('water-customers.index', ['all' => 1]) }}" class="btn btn-sm btn-link mb-2">Termasuk yang berhenti</a>
    </div>

    <div class="card">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr><th>No. pelanggan</th><th>Rumah</th><th>Meter</th><th>Sejak</th><th>Status</th><th class="text-right">Pemakaian terakhir</th></tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php $meter = $meters->get($customer->house_id); @endphp
                        @php $usage = $lastUsage->get($customer->house_id); @endphp
                        <tr>
                            <td>{{ $customer->customer_number }}</td>
                            <td><a href="{{ route('houses.show', $customer->house) }}">{{ $customer->house->house_code }}</a></td>
                            <td>{{ $meter?->meter_number ?? ($meter ? '(tanpa nomor)' : '—') }}</td>
                            <td>{{ $customer->start_date->format('d/m/Y') }}{{ $customer->end_date ? ' – '.$customer->end_date->format('d/m/Y') : '' }}</td>
                            <td><span class="badge badge-{{ $customer->status->badge() }}">{{ $customer->status->label() }}</span></td>
                            <td class="text-right">
                                @if ($usage)
                                    {{ Money::formatQuantity($usage->usage_volume) }} m³ <span class="text-muted small">({{ $usage->usage_period->translatedFormat('M Y') }})</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pelanggan air.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())
            <div class="card-footer">{{ $customers->links('partials.pagination') }}</div>
        @endif
    </div>
@stop

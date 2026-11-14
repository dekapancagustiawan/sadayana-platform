@extends('adminlte::page')

@section('title', 'Pembebasan Tagihan')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-user-clock mr-2 text-primary"></i>Pembebasan Tagihan</h1>
@stop

@section('content')
    @include('partials.flash')
    <p class="text-muted">Untuk rumah yang <strong>belum menempati (BM)</strong> atau kondisi khusus lain: tagihan tidak dibuat sampai tanggal yang ditentukan.</p>

    <div class="row">
        <div class="col-lg-8">
            <div class="card"><div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="thead-light"><tr><th>Rumah</th><th>Jenis</th><th>Berlaku</th><th>Alasan</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($exemptions as $exemption)
                            @php $active = $exemption->valid_until->gte(today()); @endphp
                            <tr @class(['text-muted' => ! $active])>
                                <td class="font-weight-bold">{{ $exemption->house->house_code }}</td>
                                <td>{{ $exemption->billing_type ? \App\Enums\Billing\InvoiceType::from($exemption->billing_type)->label() : 'Semua' }}</td>
                                <td>{{ $exemption->valid_from->format('d/m/Y') }} – {{ $exemption->valid_until->format('d/m/Y') }}</td>
                                <td>{{ $exemption->reason }}</td>
                                <td class="text-right">
                                    @if ($active)
                                        @can('billing-exemptions.manage')
                                            <form action="{{ route('billing-exemptions.end', $exemption) }}" method="POST">@csrf<button class="btn btn-xs btn-outline-danger">Akhiri</button></form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada pembebasan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
        @can('billing-exemptions.manage')
            <div class="col-lg-4">
                <form action="{{ route('billing-exemptions.store') }}" method="POST" class="card card-outline card-primary">@csrf
                    <div class="card-body">
                        <div class="form-group"><label class="small">Rumah</label>
                            <select name="house_id" class="form-control" required><option value="">— pilih —</option>@foreach ($houses as $house)<option value="{{ $house->id }}">{{ $house->house_code }}</option>@endforeach</select></div>
                        <div class="form-group"><label class="small">Jenis tagihan</label>
                            <select name="billing_type" class="form-control"><option value="">Semua jenis</option><option value="water">Air</option><option value="waste">Sampah</option><option value="contribution">Iuran</option></select></div>
                        <div class="form-row">
                            <div class="form-group col-6"><label class="small">Dari</label><input type="date" name="valid_from" class="form-control" required value="{{ today()->startOfMonth()->toDateString() }}"></div>
                            <div class="form-group col-6"><label class="small">Sampai</label><input type="date" name="valid_until" class="form-control" required value="{{ today()->endOfYear()->toDateString() }}"></div>
                        </div>
                        <div class="form-group mb-0"><label class="small">Alasan</label><input name="reason" class="form-control" required maxlength="255" value="Belum menempati (BM)"></div>
                    </div>
                    <div class="card-footer"><button class="btn btn-primary btn-block">Simpan</button></div>
                </form>
            </div>
        @endcan
    </div>
@stop

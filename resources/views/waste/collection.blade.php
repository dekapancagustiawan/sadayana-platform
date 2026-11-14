@extends('adminlte::page')

@section('title', 'Catat Sampah')

@section('content_header')
    <h1 class="m-0"><i class="fas fa-truck mr-2 text-success"></i>Catat Pengambilan Sampah</h1>
@stop

@section('content')
    @include('partials.flash')

    <form method="GET" class="mb-3">
        <label class="small mb-1" for="date">Tanggal pengambilan</label>
        <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" onchange="this.form.submit()">
    </form>

    <form action="{{ route('waste.collection.store') }}" method="POST">
        @csrf
        <input type="hidden" name="service_date" value="{{ $date->toDateString() }}">
        <p class="small text-muted mb-2">Semua rumah default <strong>Diambil</strong>. Ubah yang terlewat. Pengambilan rutin seminggu sekali.</p>
        <div class="list-group mb-3">
            @forelse ($houses as $house)
                @php $current = $records->get($house->id)?->status ?? 'completed'; @endphp
                <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <span>
                        <strong>{{ $house->house_code }}</strong>
                        <span class="d-block small text-muted">{{ $house->primaryResident()?->full_name ?? '—' }}</span>
                    </span>
                    <select name="status[{{ $house->id }}]" class="form-control form-control-sm w-auto">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @empty
                <div class="list-group-item text-muted text-center">Belum ada rumah yang berlangganan layanan sampah.</div>
            @endforelse
        </div>
        @if ($houses->isNotEmpty())
            <input type="text" name="notes" class="form-control mb-2" maxlength="500" placeholder="Catatan (opsional), mis. truk terlambat">
            <button class="btn btn-success btn-lg btn-block"><i class="fas fa-save mr-1"></i> Simpan {{ $records->isNotEmpty() ? '(perbarui)' : '' }}</button>
        @endif
    </form>
@stop

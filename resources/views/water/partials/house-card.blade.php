{{--
    Kartu "Air" di detail rumah. Variabel: $house, $waterCustomer (langganan
    terakhir atau null), $waterMeter (meter aktif atau null), $waterUsages.
--}}
@use('App\Support\Money')

@php($open = $waterCustomer && $waterCustomer->end_date === null)

<div class="card card-outline card-info">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-faucet mr-1"></i> Air</h3>
        <div class="card-tools">
            @if ($open)
                <span class="badge badge-{{ $waterCustomer->status->badge() }}">{{ $waterCustomer->status->label() }}</span>
            @else
                <span class="badge badge-light">bukan pelanggan</span>
            @endif
        </div>
    </div>
    <div class="card-body">
        @if ($open)
            <dl class="row mb-2 small">
                <dt class="col-5">No. pelanggan</dt><dd class="col-7">{{ $waterCustomer->customer_number }}</dd>
                <dt class="col-5">Sejak</dt><dd class="col-7">{{ $waterCustomer->start_date->format('d/m/Y') }}</dd>
                <dt class="col-5">Meter</dt>
                <dd class="col-7">{{ $waterMeter ? ($waterMeter->meter_number ?? '(tanpa nomor)').' · awal '.Money::formatQuantity($waterMeter->initial_reading) : 'belum ada meter aktif' }}</dd>
            </dl>

            @if ($waterUsages->isNotEmpty())
                <table class="table table-sm small mb-2">
                    <thead><tr><th>Bulan</th><th class="text-right">m³</th><th class="text-right">Tagihan</th></tr></thead>
                    <tbody>
                        @foreach ($waterUsages as $usage)
                            <tr>
                                <td>@can('meter-readings.view')<a href="{{ route('meter-readings.show', $usage->current_reading_id) }}">{{ $usage->usage_period->translatedFormat('M Y') }}</a>@else{{ $usage->usage_period->translatedFormat('M Y') }}@endcan</td>
                                <td class="text-right">{{ Money::formatQuantity($usage->usage_volume) }}</td>
                                <td class="text-right">{{ Money::format($usage->calculated_amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="small text-muted">Belum ada pembacaan meter.</p>
            @endif

            @can('water-customers.manage')
                <div class="d-flex flex-wrap">
                    @if ($waterMeter)
                        <button type="button" class="btn btn-xs btn-outline-secondary mr-1 mb-1" data-toggle="modal" data-target="#replace-meter">Ganti meter</button>
                    @endif
                    <form action="{{ route('water-customers.suspension', $waterCustomer) }}" method="POST" class="mr-1 mb-1"
                          onsubmit="return confirm('{{ $waterCustomer->status->value === 'suspended' ? 'Sambung kembali saluran air?' : 'Tandai saluran air diputus sementara?' }}');">
                        @csrf
                        <input type="hidden" name="suspended" value="{{ $waterCustomer->status->value === 'suspended' ? 0 : 1 }}">
                        <button class="btn btn-xs btn-outline-warning">{{ $waterCustomer->status->value === 'suspended' ? 'Sambung kembali' : 'Putus sementara' }}</button>
                    </form>
                    <button type="button" class="btn btn-xs btn-outline-danger mb-1" data-toggle="modal" data-target="#unsubscribe-water">Berhenti langganan</button>
                </div>
            @endcan
        @else
            <p class="small text-muted">Rumah ini tidak berlangganan air.</p>
            @can('water-customers.manage')
                <form action="{{ route('water-customers.subscribe', $house) }}" method="POST">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label class="small mb-0" for="water-start">Mulai</label>
                            <input id="water-start" type="date" name="start_date" required value="{{ now()->startOfMonth()->toDateString() }}" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-6">
                            <label class="small mb-0" for="water-initial">Angka awal meter</label>
                            <input id="water-initial" type="number" name="initial_reading" min="0" step="0.001" value="0" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="small mb-0" for="water-meter-number">No. meter (opsional)</label>
                        <input id="water-meter-number" type="text" name="meter_number" maxlength="50" class="form-control form-control-sm">
                    </div>
                    <button class="btn btn-info btn-sm btn-block"><i class="fas fa-plus mr-1"></i> Daftarkan pelanggan air</button>
                </form>
            @endcan
        @endif
    </div>
</div>

@if ($open)
    @can('water-customers.manage')
        @if ($waterMeter)
            <div class="modal fade" id="replace-meter" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" action="{{ route('water-meters.replace', $house) }}" method="POST">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">Ganti meter {{ $house->house_code }}</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                        <div class="modal-body">
                            <p class="small text-muted">Selisih angka akhir meter lama dengan pembacaan terakhirnya ikut ditagihkan pada pembacaan pertama meter baru.</p>
                            <div class="form-row">
                                <div class="form-group col-6"><label class="small">Angka akhir meter lama</label><input type="number" name="final_reading" min="0" step="0.001" required class="form-control"></div>
                                <div class="form-group col-6"><label class="small">Tanggal ganti</label><input type="date" name="replaced_at" required value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control"></div>
                                <div class="form-group col-6"><label class="small">No. meter baru</label><input type="text" name="meter_number" maxlength="50" class="form-control"></div>
                                <div class="form-group col-6"><label class="small">Angka awal meter baru</label><input type="number" name="initial_reading" min="0" step="0.001" value="0" class="form-control"></div>
                                <div class="form-group col-6"><label class="small">Meter lama</label>
                                    <select name="old_status" class="form-control"><option value="replaced">Diganti</option><option value="broken">Rusak</option></select></div>
                                <div class="form-group col-6"><label class="small">Catatan</label><input type="text" name="notes" maxlength="500" class="form-control"></div>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
                    </form>
                </div>
            </div>
        @endif
        <div class="modal fade" id="unsubscribe-water" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" action="{{ route('water-customers.unsubscribe', $waterCustomer) }}" method="POST">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">Berhenti langganan air</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                    <div class="modal-body">
                        <div class="form-group"><label class="small">Tanggal berhenti</label><input type="date" name="end_date" required value="{{ today()->toDateString() }}" class="form-control"></div>
                        <div class="form-group mb-0"><label class="small">Alasan</label><input type="text" name="reason" maxlength="500" class="form-control"></div>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-danger">Hentikan</button></div>
                </form>
            </div>
        </div>
    @endcan
@endif

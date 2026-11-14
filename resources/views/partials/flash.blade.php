{{-- Pesan flash standar (success / error / warning) untuk layar admin. --}}
@foreach (['success' => ['success', 'fa-check-circle'], 'error' => ['danger', 'fa-exclamation-circle'], 'warning' => ['warning', 'fa-exclamation-triangle']] as $key => [$type, $icon])
    @if (session($key))
        <div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
            <i class="fas {{ $icon }} mr-1"></i> {{ session($key) }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif
@endforeach

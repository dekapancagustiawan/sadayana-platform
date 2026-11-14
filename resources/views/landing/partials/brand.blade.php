{{--
    Logo + nama untuk navbar/footer.
    Logo unggahan General Settings dipakai apa adanya; bila belum ada, emblem
    Sadayana (config landing.default_mark) + nama aplikasi.
    @param bool $dark  true = di atas latar gelap (footer)
--}}
@php($dark = $dark ?? false)

@if ($site['logo_url'])
    <img src="{{ $site['logo_url'] }}" alt="{{ $site['name'] }}" class="h-9 w-auto">
@else
    <img src="{{ asset(config('landing.default_mark')) }}" alt="" class="h-9 w-auto" width="62" height="36">
    <span class="leading-tight">
        <span @class(['block font-display text-xl font-extrabold tracking-tight', 'text-white' => $dark, 'text-brand-900' => ! $dark])>{{ $site['name'] }}</span>
        <span @class(['hidden text-[11px] font-medium italic sm:block', 'text-slate-400' => $dark, 'text-brand-700' => ! $dark])>{{ __('landing.tagline') }}</span>
    </span>
@endif

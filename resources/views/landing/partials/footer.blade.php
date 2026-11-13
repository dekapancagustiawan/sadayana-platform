@php
    $socialIcons = [
        'instagram' => 'ph-instagram-logo',
        'facebook' => 'ph-facebook-logo',
        'tiktok' => 'ph-tiktok-logo',
        'youtube' => 'ph-youtube-logo',
    ];
@endphp

<footer class="bg-slate-950 text-slate-400">
    <div class="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-12 sm:px-6 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex items-center gap-2 font-bold text-white">
                @if ($site['logo_url'])
                    <img src="{{ $site['logo_url'] }}" alt="{{ $site['name'] }}" class="h-8 w-auto">
                @else
                    <span class="grid size-8 place-items-center rounded-lg bg-brand-600 text-white">{{ mb_substr($site['name'], 0, 1) }}</span>
                    <span>{{ $site['name'] }}</span>
                @endif
            </div>
            <p class="mt-3 max-w-sm text-sm">{{ __('landing.footer.tagline') }}</p>
        </div>

        @if (count($site['socials']))
            <div class="flex items-center gap-4">
                @foreach ($site['socials'] as $network => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"
                       class="grid size-10 place-items-center rounded-full bg-white/5 text-xl text-slate-300 transition hover:bg-brand-600 hover:text-white">
                        <i class="ph {{ $socialIcons[$network] }}"></i>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-6 text-xs sm:flex-row sm:px-6">
            <p>{{ __('landing.footer.copyright', ['year' => now()->year, 'app' => $site['name']]) }}</p>
            @include('landing.partials.lang-switcher')
        </div>
    </div>
</footer>

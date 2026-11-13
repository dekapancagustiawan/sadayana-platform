{{-- Pengalih bahasa berbasis session — lihat route `lang.switch`. --}}
<span class="flex items-center gap-1 text-xs font-semibold tracking-wide">
    @foreach (\App\Http\Middleware\SetLocale::SUPPORTED_LOCALES as $locale)
        @if (! $loop->first)<span class="text-slate-300">/</span>@endif
        <a href="{{ route('lang.switch', $locale) }}"
           class="{{ app()->getLocale() === $locale ? 'text-brand-600' : 'text-slate-400 hover:text-slate-700' }}">
            {{ __("landing.switcher.{$locale}") }}
        </a>
    @endforeach
</span>

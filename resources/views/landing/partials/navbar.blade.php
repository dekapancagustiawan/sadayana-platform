@php
    // Menu hanya memuat section yang benar-benar aktif di config/landing.php.
    $navItems = collect(['features', 'about', 'testimonials', 'faq'])
        ->filter(fn ($section) => in_array($section, $sections, true));
    $hasContact = in_array('contact', $sections, true);
@endphp

<header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-slate-200/70 bg-white/85 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-4 py-4 sm:px-6">
        <a href="{{ route('public.home') }}" class="flex items-center gap-2 font-bold text-slate-900">
            @if ($site['logo_url'])
                <img src="{{ $site['logo_url'] }}" alt="{{ $site['name'] }}" class="h-8 w-auto">
            @else
                <span class="grid size-8 place-items-center rounded-lg bg-brand-600 text-white">{{ mb_substr($site['name'], 0, 1) }}</span>
                <span>{{ $site['name'] }}</span>
            @endif
        </a>

        <nav class="hidden items-center gap-8 text-sm font-medium md:flex">
            @foreach ($navItems as $item)
                <a href="#{{ $item }}" class="text-slate-600 transition hover:text-brand-600">{{ __("landing.nav.{$item}") }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-4 md:flex">
            @include('landing.partials.lang-switcher')
            @if ($hasContact)
                <a href="#contact" class="rounded-full bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                    {{ __('landing.nav.cta') }}
                </a>
            @endif
        </div>

        <button type="button" class="md:hidden" @click="open = !open" :aria-expanded="open" aria-label="{{ __('landing.nav.open_menu') }}">
            <i class="ph ph-list text-2xl text-slate-900" x-show="!open"></i>
            <i class="ph ph-x text-2xl text-slate-900" x-show="open" x-cloak></i>
        </button>
    </div>

    <div x-show="open" x-cloak x-transition class="border-t border-slate-200 bg-white md:hidden">
        <nav class="mx-auto flex max-w-6xl flex-col px-4 py-3 text-sm font-medium sm:px-6">
            @foreach ($navItems as $item)
                <a href="#{{ $item }}" @click="open = false" class="border-b border-slate-100 py-3 text-slate-700">{{ __("landing.nav.{$item}") }}</a>
            @endforeach
            <div class="flex items-center justify-between py-3">
                @include('landing.partials.lang-switcher')
                @if ($hasContact)
                    <a href="#contact" @click="open = false" class="rounded-full bg-brand-600 px-5 py-2.5 font-semibold text-white">{{ __('landing.nav.cta') }}</a>
                @endif
            </div>
        </nav>
    </div>
</header>

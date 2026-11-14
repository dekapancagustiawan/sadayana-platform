@php
    // Menu hanya memuat section yang benar-benar aktif di config/landing.php.
    $navItems = collect(['features', 'workflow', 'about', 'roles', 'testimonials', 'faq'])
        ->filter(fn ($section) => in_array($section, $sections, true));
    $hasContact = in_array('contact', $sections, true);
@endphp

<header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-slate-200/70 bg-white/85 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-4 py-4 sm:px-6">
        <a href="{{ route('public.home') }}" class="flex items-center gap-2.5">
            @include('landing.partials.brand')
        </a>

        <nav class="hidden items-center gap-7 text-sm font-medium lg:flex">
            @foreach ($navItems as $item)
                <a href="#{{ $item }}" class="text-slate-600 transition hover:text-brand-600">{{ __("landing.nav.{$item}") }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-4 lg:flex">
            @include('landing.partials.lang-switcher')
            @if ($hasContact)
                <a href="#contact" class="rounded-full bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                    {{ __('landing.nav.cta') }}
                </a>
            @endif
        </div>

        <button type="button" class="lg:hidden" @click="open = !open" :aria-expanded="open" aria-label="{{ __('landing.nav.open_menu') }}">
            <i class="ph ph-list text-2xl text-slate-900" x-show="!open"></i>
            <i class="ph ph-x text-2xl text-slate-900" x-show="open" x-cloak></i>
        </button>
    </div>

    <div x-show="open" x-cloak x-transition class="border-t border-slate-200 bg-white lg:hidden">
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

@php($heroImage = config('landing.hero_image'))

<section id="top" class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
    {{-- Ornamen: matahari, air, daun — diambil dari logo. --}}
    <div aria-hidden="true" class="pointer-events-none absolute -top-24 right-[-6rem] size-[26rem] rounded-full bg-sun/25 blur-3xl"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -left-24 top-1/3 size-[22rem] rounded-full bg-brand-300/30 blur-3xl"></div>
    <div aria-hidden="true" class="pointer-events-none absolute bottom-0 right-1/4 size-64 rounded-full bg-leaf-500/15 blur-3xl"></div>

    <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pb-20 pt-14 sm:px-6 md:pt-20 lg:grid-cols-2 lg:pb-24">
        <div data-aos="fade-up">
            <p class="inline-flex items-center gap-2 rounded-full bg-leaf-50 px-4 py-1.5 text-sm font-semibold italic text-leaf-700 ring-1 ring-leaf-100">
                <i class="ph-fill ph-leaf"></i>
                {{ __('landing.hero.eyebrow', ['app' => $site['name']]) }}
            </p>
            <h1 class="mt-6 font-display text-4xl font-extrabold leading-tight tracking-tight text-brand-900 sm:text-5xl lg:text-[3.4rem]">
                {{ __('landing.hero.title') }}
            </h1>
            <p class="mt-6 text-lg leading-relaxed text-slate-600">
                {{ __('landing.hero.subtitle', ['app' => $site['name']]) }}
            </p>

            <div class="mt-9 flex flex-wrap gap-4">
                @if (in_array('contact', $sections, true))
                    <a href="#contact" class="inline-flex items-center gap-2 rounded-full bg-brand-900 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-900/20 transition hover:bg-brand-800">
                        <i class="ph ph-chat-circle-dots text-lg"></i>
                        {{ __('landing.hero.cta_primary') }}
                    </a>
                @endif
                @if (in_array('features', $sections, true))
                    <a href="#features" class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white px-6 py-3 text-sm font-semibold text-brand-800 transition hover:border-brand-300 hover:bg-brand-50">
                        {{ __('landing.hero.cta_secondary') }}
                        <i class="ph ph-arrow-down"></i>
                    </a>
                @endif
            </div>

            <ul class="mt-9 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-slate-600">
                @foreach (__('landing.hero.highlights') as $highlight)
                    <li class="flex items-center gap-2">
                        <i class="ph-fill ph-check-circle text-lg text-leaf-500"></i>
                        {{ $highlight }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="relative mx-auto w-full max-w-md lg:max-w-none" data-aos="zoom-in" data-aos-delay="150">
            <div aria-hidden="true" class="absolute inset-6 rounded-[2.5rem] bg-gradient-to-br from-brand-100 via-white to-leaf-50 rotate-3"></div>
            <div class="relative rounded-[2.5rem] bg-white/90 p-8 shadow-2xl shadow-brand-900/10 ring-1 ring-brand-100 sm:p-10">
                @if ($heroImage)
                    <img src="{{ asset($heroImage) }}" alt="" class="w-full rounded-3xl object-cover">
                @else
                    <img src="{{ asset(config('landing.default_logo')) }}" alt="{{ $site['name'] }} — {{ __('landing.tagline') }}"
                         class="mx-auto w-full max-w-sm" width="736" height="513">
                @endif
            </div>
        </div>
    </div>

    {{-- Gelombang air di batas bawah hero. --}}
    <svg aria-hidden="true" class="absolute inset-x-0 bottom-0 h-12 w-full text-white sm:h-16" viewBox="0 0 1440 80" preserveAspectRatio="none" fill="currentColor">
        <path d="M0 48c120-26 240-26 360 0s240 26 360 0 240-26 360 0 240 26 360 0v32H0z"/>
    </svg>
</section>

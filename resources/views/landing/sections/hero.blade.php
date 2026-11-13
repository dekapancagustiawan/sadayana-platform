@php($heroImage = config('landing.hero_image'))

<section id="top" class="relative overflow-hidden bg-brand-950">
    @if ($heroImage)
        <img src="{{ asset($heroImage) }}" alt="" class="absolute inset-0 size-full object-cover opacity-30">
    @else
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,var(--color-brand-600),transparent_60%)] opacity-70"></div>
    @endif

    <div class="relative mx-auto max-w-6xl px-4 py-24 sm:px-6 md:py-36">
        <div class="max-w-2xl" data-aos="fade-up">
            <p class="text-sm font-semibold uppercase tracking-widest text-accent">
                {{ __('landing.hero.eyebrow', ['app' => $site['name']]) }}
            </p>
            <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-white sm:text-5xl md:text-6xl">
                {{ __('landing.hero.title') }}
            </h1>
            <p class="mt-6 text-lg leading-relaxed text-brand-100">
                {{ __('landing.hero.subtitle') }}
            </p>
            <div class="mt-10 flex flex-wrap gap-4">
                @if (in_array('contact', $sections, true))
                    <a href="#contact" class="rounded-full bg-white px-6 py-3 text-sm font-semibold text-brand-700 shadow transition hover:bg-brand-50">
                        {{ __('landing.hero.cta_primary') }}
                    </a>
                @endif
                @if (in_array('features', $sections, true))
                    <a href="#features" class="rounded-full border border-white/30 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                        {{ __('landing.hero.cta_secondary') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>

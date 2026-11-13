<section id="features" class="py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center" data-aos="fade-up">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('landing.features.eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ __('landing.features.title') }}</h2>
            <p class="mt-4 text-slate-500">{{ __('landing.features.subtitle') }}</p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (config('landing.features') as $key => $icon)
                <div class="rounded-2xl border border-slate-200 p-6 transition hover:-translate-y-1 hover:shadow-lg"
                     data-aos="fade-up" data-aos-delay="{{ $loop->index * 60 }}">
                    <span class="grid size-12 place-items-center rounded-xl bg-brand-50 text-2xl text-brand-600">
                        <i class="ph {{ $icon }}"></i>
                    </span>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __("landing.features.items.{$key}.title") }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ __("landing.features.items.{$key}.desc") }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

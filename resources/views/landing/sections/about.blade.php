<section id="about" class="bg-slate-50 py-20 md:py-28">
    <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 sm:px-6 md:grid-cols-2">
        <div data-aos="fade-right">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('landing.about.eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ __('landing.about.title') }}</h2>
            <p class="mt-6 leading-relaxed text-slate-600">{{ __('landing.about.body', ['app' => $site['name']]) }}</p>
        </div>

        <div class="grid grid-cols-3 gap-4" data-aos="fade-left">
            @foreach (config('landing.stats') as $key => $value)
                <div class="rounded-2xl bg-white p-6 text-center shadow-sm">
                    <p class="text-3xl font-extrabold text-brand-600 sm:text-4xl">{{ $value }}</p>
                    <p class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">{{ __("landing.about.stats.{$key}") }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

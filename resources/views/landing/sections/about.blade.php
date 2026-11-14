@php($valueIcons = ['asih' => 'ph-heart', 'asah' => 'ph-lightbulb', 'asuh' => 'ph-shield-check'])

<section id="about" class="py-20 md:py-28">
    <div class="mx-auto grid max-w-6xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2">
        <div data-aos="fade-right">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('landing.about.eyebrow', ['app' => $site['name']]) }}</p>
            <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">{{ __('landing.about.title') }}</h2>
            <p class="mt-6 leading-relaxed text-slate-600">{{ __('landing.about.body', ['app' => $site['name']]) }}</p>

            <div class="mt-10 grid grid-cols-3 gap-4">
                @foreach (config('landing.stats') as $key => $value)
                    <div class="rounded-2xl bg-brand-50 p-5 text-center ring-1 ring-brand-100">
                        <p class="font-display text-3xl font-black text-brand-800 sm:text-4xl">{{ $value }}</p>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">{{ __("landing.about.stats.{$key}") }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-4" data-aos="fade-left">
            @foreach (__('landing.about.values') as $key => $value)
                <div class="flex gap-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-leaf-50 text-2xl text-leaf-600 ring-1 ring-leaf-100">
                        <i class="ph-duotone {{ $valueIcons[$key] ?? 'ph-sparkle' }}"></i>
                    </span>
                    <div>
                        <h3 class="font-display text-lg font-bold italic text-brand-900">{{ $value['title'] }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ $value['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

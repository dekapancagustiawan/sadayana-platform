<section id="roles" class="bg-slate-50 py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center" data-aos="fade-up">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('landing.roles.eyebrow') }}</p>
            <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">{{ __('landing.roles.title') }}</h2>
            <p class="mt-4 text-slate-500">{{ __('landing.roles.subtitle') }}</p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (config('landing.roles') as $key => $icon)
                <div class="flex flex-col rounded-3xl bg-white p-7 shadow-sm ring-1 ring-slate-200" data-aos="fade-up" data-aos-delay="{{ $loop->index * 70 }}">
                    <span class="grid size-12 place-items-center rounded-full bg-brand-900 text-2xl text-white">
                        <i class="ph {{ $icon }}"></i>
                    </span>
                    <h3 class="mt-5 font-display text-lg font-bold text-brand-900">{{ __("landing.roles.items.{$key}.title") }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ __("landing.roles.items.{$key}.desc") }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

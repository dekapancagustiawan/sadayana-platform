<section id="workflow" class="relative overflow-hidden bg-brand-900 py-20 text-white md:py-28">
    <div aria-hidden="true" class="pointer-events-none absolute -right-32 -top-32 size-96 rounded-full bg-brand-500/30 blur-3xl"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 left-10 size-96 rounded-full bg-leaf-500/15 blur-3xl"></div>

    <div class="relative mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center" data-aos="fade-up">
            <p class="text-sm font-semibold uppercase tracking-widest text-sun">{{ __('landing.workflow.eyebrow') }}</p>
            <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('landing.workflow.title') }}</h2>
            <p class="mt-4 text-brand-100">{{ __('landing.workflow.subtitle') }}</p>
        </div>

        <ol class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            @foreach (config('landing.workflow') as $key => $icon)
                <li class="relative rounded-3xl bg-white/5 p-6 ring-1 ring-white/10" data-aos="fade-up" data-aos-delay="{{ $loop->index * 90 }}">
                    <div class="flex items-start justify-between">
                        <span class="grid size-14 place-items-center rounded-2xl bg-white text-2xl text-brand-700 shadow-lg shadow-black/10">
                            <i class="ph-duotone {{ $icon }}"></i>
                        </span>
                        <span class="font-display text-4xl font-black leading-none text-white/20">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <h3 class="mt-6 font-display text-xl font-bold">{{ __("landing.workflow.steps.{$key}.title") }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-brand-100">{{ __("landing.workflow.steps.{$key}.desc") }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section id="testimonials" class="py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center" data-aos="fade-up">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('landing.testimonials.eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ __('landing.testimonials.title') }}</h2>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-3">
            @foreach (__('landing.testimonials.items') as $item)
                <figure class="flex flex-col rounded-2xl border border-slate-200 p-6" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                    <i class="ph-fill ph-quotes text-3xl text-brand-200"></i>
                    <blockquote class="mt-4 flex-1 leading-relaxed text-slate-600">
                        {{ str_replace(':app', $site['name'], $item['quote']) }}
                    </blockquote>
                    <figcaption class="mt-6 flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-full bg-brand-100 font-semibold text-brand-700">
                            {{ mb_substr($item['name'], 0, 1) }}
                        </span>
                        <span>
                            <span class="block font-semibold text-slate-900">{{ $item['name'] }}</span>
                            <span class="block text-sm text-slate-500">{{ $item['role'] }}</span>
                        </span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>

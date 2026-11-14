@php
    // Warna kartu mengikuti unsur logo: air (brand), daun (leaf), matahari (sun).
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600 ring-brand-100',
        'leaf' => 'bg-leaf-50 text-leaf-600 ring-leaf-100',
        'sun' => 'bg-amber-50 text-accent ring-amber-100',
    ];
@endphp

<section id="features" class="py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mx-auto max-w-2xl text-center" data-aos="fade-up">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('landing.features.eyebrow') }}</p>
            <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">{{ __('landing.features.title') }}</h2>
            <p class="mt-4 text-slate-500">{{ __('landing.features.subtitle') }}</p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (config('landing.features') as $key => $feature)
                @php([$icon, $tone] = is_array($feature) ? $feature : [$feature, 'brand'])
                <div class="group rounded-3xl border border-slate-200 bg-white p-7 transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl hover:shadow-brand-900/5"
                     data-aos="fade-up" data-aos-delay="{{ $loop->index * 60 }}">
                    <span class="grid size-13 place-items-center rounded-2xl text-2xl ring-1 {{ $tones[$tone] ?? $tones['brand'] }}">
                        <i class="ph-duotone {{ $icon }}"></i>
                    </span>
                    <h3 class="mt-5 font-display text-xl font-bold text-brand-900">{{ __("landing.features.items.{$key}.title") }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ __("landing.features.items.{$key}.desc") }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

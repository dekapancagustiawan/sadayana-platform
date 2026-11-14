<section id="faq" class="bg-slate-50 py-20 md:py-28">
    <div class="mx-auto max-w-3xl px-4 sm:px-6">
        <div class="text-center" data-aos="fade-up">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('landing.faq.eyebrow') }}</p>
            <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">{{ __('landing.faq.title') }}</h2>
        </div>

        <div class="mt-12 space-y-3" x-data="{ open: 0 }">
            @foreach (__('landing.faq.items') as $item)
                <div class="rounded-2xl border border-slate-200 bg-white">
                    <button type="button" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left font-semibold text-slate-900"
                            @click="open = open === {{ $loop->index }} ? null : {{ $loop->index }}"
                            :aria-expanded="open === {{ $loop->index }}">
                        <span>{{ str_replace(':app', $site['name'], $item['q']) }}</span>
                        <i class="ph ph-caret-down shrink-0 text-slate-400 transition" :class="open === {{ $loop->index }} && 'rotate-180'"></i>
                    </button>
                    <div x-show="open === {{ $loop->index }}" x-transition.opacity @if (! $loop->first) x-cloak @endif>
                        <p class="px-5 pb-5 leading-relaxed text-slate-600">{{ str_replace(':app', $site['name'], $item['a']) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

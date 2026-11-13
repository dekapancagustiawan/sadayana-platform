@php
    $contactItems = array_filter([
        ['icon' => 'ph-envelope-simple', 'label' => __('landing.contact.email'), 'value' => $site['email'], 'href' => $site['email'] ? "mailto:{$site['email']}" : null],
        ['icon' => 'ph-phone', 'label' => __('landing.contact.phone'), 'value' => $site['phone'], 'href' => $site['phone'] ? 'tel:'.preg_replace('/[^0-9+]/', '', $site['phone']) : null],
        ['icon' => 'ph-whatsapp-logo', 'label' => __('landing.contact.whatsapp'), 'value' => $site['whatsapp'] ? '+'.$site['whatsapp'] : null, 'href' => $site['whatsapp_url']],
        ['icon' => 'ph-map-pin', 'label' => __('landing.contact.address'), 'value' => $site['address'], 'href' => null],
    ], fn ($item) => filled($item['value']));
@endphp

<section id="contact" class="py-20 md:py-28">
    <div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-5">
        <div class="lg:col-span-2" data-aos="fade-right">
            <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">{{ __('landing.contact.eyebrow') }}</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ __('landing.contact.title') }}</h2>
            <p class="mt-4 text-slate-500">{{ __('landing.contact.subtitle') }}</p>

            @if (count($contactItems))
                <ul class="mt-10 space-y-5">
                    @foreach ($contactItems as $item)
                        <li class="flex items-start gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-xl text-brand-600">
                                <i class="ph {{ $item['icon'] }}"></i>
                            </span>
                            <span>
                                <span class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $item['label'] }}</span>
                                @if ($item['href'])
                                    <a href="{{ $item['href'] }}" class="font-medium text-slate-800 hover:text-brand-600" @if (str_starts_with($item['href'], 'https://')) target="_blank" rel="noopener" @endif>{{ $item['value'] }}</a>
                                @else
                                    <span class="block whitespace-pre-line font-medium text-slate-800">{{ $item['value'] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($site['whatsapp_url'])
                <a href="{{ $site['whatsapp_url'] }}" target="_blank" rel="noopener"
                   class="mt-8 inline-flex items-center gap-2 rounded-full bg-emerald-500 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-600">
                    <i class="ph ph-whatsapp-logo text-lg"></i> {{ __('landing.contact.whatsapp_cta') }}
                </a>
            @endif
        </div>

        @if (config('landing.contact_form'))
            <div class="lg:col-span-3" data-aos="fade-left">
                <form action="{{ route('public.contact.store') }}#contact" method="POST"
                      class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    @csrf

                    @if (session('contact_status') === 'sent')
                        <div class="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
                            {{ __('landing.contact.form.sent') }}
                        </div>
                    @elseif (session('contact_status') === 'failed')
                        <div class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                            {{ __('landing.contact.form.failed') }}
                        </div>
                    @endif

                    {{-- Honeypot: disembunyikan dari manusia, sering diisi bot. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="contact-name" class="text-sm font-medium text-slate-700">{{ __('landing.contact.form.name') }}</label>
                            <input id="contact-name" type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                                   class="mt-1.5 w-full rounded-lg border px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 @error('name') border-red-400 @else border-slate-300 @enderror">
                            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="contact-email" class="text-sm font-medium text-slate-700">{{ __('landing.contact.form.email') }}</label>
                            <input id="contact-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255"
                                   class="mt-1.5 w-full rounded-lg border px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 @error('email') border-red-400 @else border-slate-300 @enderror">
                            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="contact-phone" class="text-sm font-medium text-slate-700">{{ __('landing.contact.form.phone') }}</label>
                            <input id="contact-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="30"
                                   class="mt-1.5 w-full rounded-lg border px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 @error('phone') border-red-400 @else border-slate-300 @enderror">
                            @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="contact-message" class="text-sm font-medium text-slate-700">{{ __('landing.contact.form.message') }}</label>
                            <textarea id="contact-message" name="message" rows="5" required maxlength="2000"
                                      class="mt-1.5 w-full rounded-lg border px-4 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 @error('message') border-red-400 @else border-slate-300 @enderror">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    @include('partials.recaptcha', ['action' => 'contact'])
                    @error('g-recaptcha-response')<p class="mt-3 text-xs text-red-600">{{ $message }}</p>@enderror

                    <button type="submit" class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-700 sm:w-auto">
                        <i class="ph ph-paper-plane-tilt"></i> {{ __('landing.contact.form.submit') }}
                    </button>
                </form>
            </div>
        @endif
    </div>
</section>

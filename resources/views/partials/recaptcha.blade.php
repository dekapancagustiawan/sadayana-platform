{{--
    reCAPTCHA v3 — sisipkan di dalam <form>. Tidak menampilkan apa pun saat
    captcha mati (lihat App\Support\Captcha).

    @include('partials.recaptcha', ['action' => 'login'])
--}}
@if (\App\Support\Captcha::enabled())
    @php($recaptchaId = 'recaptcha-'.($action ?? 'submit'))
    <input type="hidden" name="{{ \App\Support\Captcha::FIELD }}" id="{{ $recaptchaId }}">
    @once
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('captcha.sitekey') }}"></script>
    @endonce
    <script>
        (function () {
            var input = document.getElementById(@json($recaptchaId));
            var form = input.closest('form');

            // Token v3 kedaluwarsa setelah 2 menit, jadi diminta tepat saat
            // submit — bukan saat halaman dimuat.
            form.addEventListener('submit', function (event) {
                if (input.value) {
                    return;
                }

                event.preventDefault();
                grecaptcha.ready(function () {
                    grecaptcha.execute(@json(config('captcha.sitekey')), { action: @json($action ?? 'submit') })
                        .then(function (token) {
                            input.value = token;
                            form.submit();
                        });
                });
            });
        })();
    </script>
@endif
